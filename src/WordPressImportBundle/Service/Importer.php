<?php

declare(strict_types=1);

/*
 * This file is part of the WordPressImport Bundle.
 *
 * (c) inspiredminds <https://github.com/inspiredminds>
 */

namespace WordPressImportBundle\Service;

use Contao\CommentsModel;
use Contao\Config;
use Contao\ContentModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Dbafs;
use Contao\FilesModel;
use Contao\NewsArchiveModel;
use Contao\NewsModel;
use Contao\StringUtil;
use Contao\System;
use Contao\UserModel;
use Doctrine\DBAL\Connection;
use GuzzleHttp\Client;
use NewsCategories\NewsCategoryModel;

class Importer
{
    /**
     * API endpoint for posts.
     */
    public const API_POSTS = 'wp-json/wp/v2/posts';

    /**
     * API endpoint for categories.
     */
    public const API_CATEGORIES = 'wp-json/wp/v2/categories';

    /**
     * API endpoint for users (authors).
     */
    public const API_USERS = 'wp-json/wp/v2/users';

    /**
     * API endpoint for media (images).
     */
    public const API_MEDIA = 'wp-json/wp/v2/media';

    /**
     * API endpoint for comments.
     */
    public const API_COMMENTS = 'wp-json/wp/v2/comments';

    /**
     * Database connection.
     *
     * @var Connection
     */
    protected $db;

    /**
     * Contao framework service.
     *
     * @var ContaoFramework
     */
    protected $framework;

    /**
     * Constructor for Importer service.
     *
     * @param Connection $db Database connection
     */
    public function __construct(Connection $db, ContaoFramework $framework)
    {
        $this->db = $db;
        $this->framework = $framework;
    }

    /**
     * Executes the import for all Contao news archives.
     *
     * @param mixed|null $intLimit
     *
     * @return array Import result
     */
    public function import($intLimit = null, $blnCronOnly = false)
    {
        // initialize the Contao framework
        $this->framework->initialize();

        // prepare the result
        $arrResult = [];

        // go through each news archive with active WordPress import
        if (null !== ($objArchives = NewsArchiveModel::findByWpImport('1'))) {
            foreach ($objArchives as $objArchive) {
                // check for cron only archives
                if ($blnCronOnly && !$objArchive->wpImportCron) {
                    continue;
                }

                // check if a wordpress URL and import folder is present
                if (!$objArchive->wpImportUrl || !$objArchive->wpImportFolder) {
                    continue;
                }

                // create HTTP client, taking care of the trailing slash in wpImportUrl
                $client = new Client([
                    'base_uri' => $objArchive->wpImportUrl.('/' !== mb_substr($objArchive->wpImportUrl, -1) ? '/' : ''),
                    'headers' => ['Accept' => 'application/json'],
                ]);

                // get the posts from WordPress
                $arrResult = $this->getPostsFromWordpress($client, $objArchive, $intLimit);

                // go through each post
                foreach ($arrResult as $objPost) {
                    // import the news
                    $this->importNews($client, $objPost, $objArchive);
                }
            }
        }

        return $arrResult;
    }

    /**
     * Get wordpress posts from given URL.
     *
     * @param mixed|null $intLimit
     *
     * @return array
     */
    protected function getPostsFromWordpress(Client $client, NewsArchiveModel $objArchive, $intLimit = null)
    {
        // set maximum for limit
        $intLimit = $intLimit ? min(100, $intLimit) : null;

        // prepare result array
        $arrReturn = [];

        // initial offset and per_page
        $offset = 0;
        $per_page = $intLimit ?: 100;

        do {
            // get the result for the page
            $arrResult = $this->request($client, self::API_POSTS, ['per_page' => $per_page, 'offset' => $offset]);

            // check for array
            if (!\is_array($arrResult)) {
                break;
            }

            // go through each result
            foreach ($arrResult as $objPost) {
                // check if post already exists
                if (0 === NewsModel::countBy(['wpPostId = ?', 'pid = ?'], [$objPost->id, $objArchive->id])) {
                    $arrReturn[] = $objPost;
                }
            }

            // check for limit
            if ($intLimit && \count($arrReturn) >= $intLimit) {
                break;
            }

            // increase offset
            $offset += $per_page;

            // run while there are results from the API
        } while ($arrResult);

        // return the results
        return $arrReturn;
    }

    /**
     * Perform requests to API.
     *
     * @return object
     */
    protected function request(Client $client, string $endpoint, array $params = [])
    {
        $json = $client->get($endpoint, ['query' => $params])->getBody()->getContents();

        // Remove hidden characters from json (https://stackoverflow.com/questions/17219916/json-decode-returns-json-error-syntax-but-online-formatter-says-the-json-is-ok)
        for ($i = 0; $i <= 31; ++$i) {
            $json = str_replace(\chr($i), '', $json);
        }
        $json = str_replace(\chr(127), '', $json);

        if (0 === strpos(bin2hex($json), 'efbbbf')) {
            $json = substr($json, 3);
        }

        return json_decode($json);
    }

    /**
     * Imports the categories for the news object.
     */
    protected function importNews(Client $client, $objPost, NewsArchiveModel $objArchive): void
    {
        // only import posts
        if ('post' !== $objPost->type) {
            return;
        }

        // get the target folder
        $strTargetFolder = FilesModel::findOneByUuid($objArchive->wpImportFolder)->path;

        // create new news
        $objNews = new NewsModel();
        $objNews->pid = $objArchive->id;
        $objNews->tstamp = time();
        $objNews->date = strtotime($objPost->date_gmt);
        $objNews->time = strtotime($objPost->date_gmt);
        $objNews->published = (('publish' === $objPost->status) ? '1' : '');
        $objNews->teaser = $this->processHtml($objPost->excerpt->rendered, $strTargetFolder);
        $objNews->headline = strip_tags($objPost->title->rendered);
        $objNews->source = 'default';
        $objNews->floating = 'above';
        $objNews->alias = $objPost->slug;
        $objNews->wpPostId = $objPost->id;
        $objNews->noComments = ('closed' !== $objPost->comment_status ? '' : '1');
        $objNews->author = $objArchive->wpDefaultAuthor;

        if (null !== NewsModel::findOneByAlias($objNews->alias)) {
            $objNews->alias .= '-'.$objPost->id;
        }

        // save the news
        $objNews->save();

        // import the teaser image
        $this->importImage($client, $objPost, $objNews, $objArchive, $strTargetFolder);

        // import the detail text
        if ($objPost->content && $objPost->content->rendered) {
            $objContent = new ContentModel();
            $objContent->ptable = NewsModel::getTable();
            $objContent->sorting = 128;
            $objContent->tstamp = time();
            $objContent->pid = $objNews->id;
            $objContent->type = 'text';
            $objContent->text = $this->processHtml($objPost->content->rendered, $strTargetFolder);
            $objContent->save();
        }

        // import the categories
        $this->importCategories($client, $objPost, $objNews, $objArchive);

        // import the authors
        $this->importAuthor($client, $objPost, $objNews, $objArchive);

        // import comments
        $this->importComments($client, $objPost, $objNews);
    }

    /**
     * Downloads the featured_media of the post and adds as a teaser image.
     * If it does not exist, it takes the first image from the content.
     */
    protected function importImage(Client $client, $objPost, NewsModel $objNews, NewsArchiveModel $objArchive, $strTargetFolder): void
    {
        if (!$objPost->featured_media) {

            // no explicit teaser image defined, take the first from the content
            $dom = new \PHPHtmlParser\Dom();
            $dom->load($objPost->content->rendered);

            // find the first image
            $img = $dom->find('img', 0);

            if (!$img) {
                return;
            }

            // download
            $objFile = $this->downloadFile($img->getAttribute('src'), $strTargetFolder);

            // check if file exists
            if ($objFile) {
                $objNews->addImage = '1';
                $objNews->singleSRC = $objFile->uuid;
                $objNews->save();
            }
            return;
        }

        $objMedia = $this->request($client, self::API_MEDIA.'/'.$objPost->featured_media);

        if (!$objMedia) {
            return;
        }

        if ('image' !== $objMedia->media_type) {
            return;
        }

        // download
        $objFile = $this->downloadFile($objMedia->source_url, $strTargetFolder);

        // check if file exists
        if ($objFile) {
            $objNews->addImage = '1';
            $objNews->singleSRC = $objFile->uuid;
            $objNews->save();
        }
    }

    /**
     * Downloads a file.
     *
     * @param string $strUrl
     * @param string $strTargetFolder
     *
     * @return FilesModel|null
     */
    protected function downloadFile($strUrl, $strTargetFolder)
    {
        if (!$strUrl || !$strTargetFolder) {
            return null;
        }

        // decode the url

        // get the url info
        $objUrlinfo = (object) parse_url($strUrl);

        // get the path info
        $objPathinfo = (object) pathinfo($objUrlinfo->path);

        // we only allow the download of files that have an extension
        if (!$objPathinfo->extension) {
            return null;
        }

        // determine the subfolder
        $strSubFolder = trim(str_replace('wp-content/uploads', '', $objPathinfo->dirname), '/');

        // determine the filename
        $strFileName = $objPathinfo->filename.'-'.substr(md5($strUrl), 0, 8).'.'.$objPathinfo->extension;

        // determine the full (relative) file path
        $strFilePath = $strTargetFolder.'/'.$strSubFolder.'/'.$strFileName;

        // check if file exists already
        if (file_exists(TL_ROOT.'/'.$strFilePath)) {
            return Dbafs::addResource($strFilePath);
        }

        // check if subdirectory exists
        if (!file_exists(TL_ROOT.'/'.$strTargetFolder.'/'.$strSubFolder)) {
            mkdir(TL_ROOT.'/'.$strTargetFolder.'/'.$strSubFolder, 0777, true);
        }

        // download the file
        try {
            (new Client())->get($strUrl, ['sink' => TL_ROOT.'/'.$strFilePath]);
        } catch (\Exception $e) {
            System::log('Error while downloading "'.$strUrl.'": '.StringUtil::substr($e->getMessage(), 280), __METHOD__, TL_ERROR);

            return null;
        }

        if (file_exists(TL_ROOT.'/'.$strFilePath)) {
            return Dbafs::addResource($strFilePath);
        }

        return null;
    }

    /**
     * Processes the text and looks for any src and srcset attributes,
     * downloads the images and replacs them with {{file::*}} insert tags.
     *
     * @param string $strText
     *
     * @return string
     */
    protected function processHtml($strText, $strTargetFolder)
    {
        // strip tags to certain allowed tags
        $strText = strip_tags($strText, Config::get('allowedTags'));

        // parse the text
        $dom = new \PHPHtmlParser\Dom();
        $dom->load($strText);

        // find all images
        $imgs = $dom->find('img');

        // go through each image
        foreach ($imgs as $img) {
            // check if image has src
            if ($img->getAttribute('src')) {
                // download the src
                if (null !== ($objFile = $this->downloadFile($img->getAttribute('src'), $strTargetFolder))) {
                    // set insert tags
                    $img->setAttribute('src', '{{file::'.StringUtil::binToUuid($objFile->uuid).'}}');
                }
            }

            // check if image has srcset
            if ($img->getAttribute('srcset')) {
                // explode
                $arrSrcset = array_map('trim', explode(',', $img->getAttribute('srcset')));

                // go through each srcset
                foreach ($arrSrcset as &$srcdesc) {
                    // explode
                    $arrSrcdesc = explode(' ', $srcdesc);

                    // must be 2
                    if (2 === \count($arrSrcdesc)) {
                        // download the src
                        if (null !== ($objFile = $this->downloadFile($arrSrcdesc[0], $strTargetFolder))) {
                            // set the new src
                            $arrSrcdesc[0] = '{{file::'.StringUtil::binToUuid($objFile->uuid).'}}';
                        }
                    }

                    // set srcdesc again
                    $srcdesc = implode(' ', $arrSrcdesc);
                }

                // set srcset
                $img->setAttribute('srcset', implode(', ', $arrSrcset));
            }

            // check if surrounded by a link in which case that would be a lightbox
            if ($img->getParent()->getTag()->name() === 'a' && ($imgUrl = $img->getParent()->getAttribute('href'))) {
                // download the image
                if (null !== ($objFile = $this->downloadFile($imgUrl, $strTargetFolder))) {

                    // set the new src
                    $img->getParent()->setAttribute('href', '{{file::'.StringUtil::binToUuid($objFile->uuid).'}}');
                    // mark it as lightbox
                    $img->getParent()->setAttribute('data-lightbox', 'true');
                }
            }
        }

        // return the dom as string
        return (string) $dom;
    }

    /**
     * Imports categories for a news item.
     *
     * @param object $objPost
     */
    protected function importAuthor(Client $client, $objPost, NewsModel $objNews, NewsArchiveModel $objArchive): void
    {
        if (!$objArchive->wpImportAuthors) {
            return;
        }

        $objAuthor = $this->request($client, self::API_USERS.'/'.$objPost->author);

        if (!$objAuthor) {
            return;
        }

        // check if there is an existing user
        $objUser = UserModel::findOneByName($objAuthor->name);

        if (null === $objUser) {
            $objUser = new UserModel();
            $objUser->tstamp = time();
            $objUser->dateAdded = time();
            $objUser->name = $objAuthor->name;
            $objUser->disable = '1';
            $objUser->save();
        }

        $objNews->author = $objUser->id;
        $objNews->save();
    }

    /**
     * Imports categories for a news item.
     *
     * @param object $objPost
     */
    protected function importCategories(Client $client, $objPost, NewsModel $objNews, NewsArchiveModel $objArchive): void
    {
        // only import categories if news_categories extension is present
        if (!\in_array('news_categories', array_keys(System::getContainer()->getParameter('kernel.bundles')), true)) {
            return;
        }

        // process the categories
        $arrCategories = StringUtil::deserialize($objNews->categories, true);

        // go through each category
        foreach ($objPost->categories as $intCategoryId) {
            // import the category
            if (null !== ($objCategory = $this->importCategory($intCategoryId, $client, $objArchive))) {
                // check if news is not already assigned to category
                if (!$this->db->fetchAll('SELECT * FROM tl_news_categories WHERE category_id = ? AND news_id = ?', [$objCategory->id, $objNews->id])) {
                    $this->db->insert('tl_news_categories', ['category_id' => $objCategory->id, 'news_id' => $objNews->id]);
                }

                // add to array
                $arrCategories[] = $objCategory->id;
            }
        }

        // save in news object
        $objNews->categories = serialize(array_unique($arrCategories));
        $objNews->save();
    }

    /**
     * Imports a category from the API.
     *
     * @param int              $intCategoryId
     * @param Client           $client
     * @param NewsArchiveModel $objArchive
     *
     * @return NewsCategoryModel|null
     */
    protected function importCategory($intCategoryId, $client, $objArchive)
    {
        $objWPCategory = $this->request($client, self::API_CATEGORIES.'/'.$intCategoryId);

        if (!$objWPCategory) {
            return null;
        }

        // get the title
        $strCategoryTitle = html_entity_decode($objWPCategory->name);

        // try to get existing category
        $objCategory = NewsCategoryModel::findOneByTitle($strCategoryTitle);

        // check if category was not found
        if (null === $objCategory) {
            // determine the parent ID
            $intParent = $objArchive->wpImportCategory ?: 0;

            // check if category has a parent
            if ($objWPCategory->parent) {
                // import the parent
                $objParent = $this->importCategory($objWPCategory->parent, $client, $objArchive);

                // set the ID to the parent
                $intParent = $objParent->id;
            }

            // create new category
            $objCategory = new NewsCategoryModel();
            $objCategory->title = $strCategoryTitle;
            $objCategory->sorting = 128;
            $objCategory->pid = $intParent;
            $objCategory->tstamp = time();
            $objCategory->alias = StringUtil::generateAlias($strCategoryTitle);
            $objCategory->published = '1';
            $objCategory->save();
        }

        // return the category
        return $objCategory;
    }

    /**
     * Imports comments for a news item.
     *
     * @param object $objPost
     */
    protected function importComments(Client $client, $objPost, NewsModel $objNews): void
    {
        // only import comments, if the ContaoCommentsBundle is available
        if (!\in_array('ContaoCommentsBundle', array_keys(System::getContainer()->getParameter('kernel.bundles')), true)) {
            return;
        }

        // retreive the comments for the post
        $arrComments = $this->request($client, self::API_COMMENTS, ['post' => $objPost->id]);

        // go through each comment
        foreach ($arrComments as $objWPComment) {
            $objComment = new CommentsModel();
            $objComment->tstamp = time();
            $objComment->source = 'tl_news';
            $objComment->parent = $objNews->id;
            $objComment->date = strtotime($objWPComment->date_gmt);
            $objComment->name = $objWPComment->author_name;
            $objComment->website = $objWPComment->author_url;
            $objComment->comment = $objWPComment->content->rendered;
            $objComment->published = ('approved' === $objWPComment->status);
            $objComment->save();
        }
    }
}
