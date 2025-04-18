<?php

declare(strict_types=1);

/*
 * This file is part of the WordPressImport Bundle.
 *
 * (c) inspiredminds <https://github.com/inspiredminds>
 */

namespace WordPressImportBundle\Service;

use Codefog\NewsCategoriesBundle\CodefogNewsCategoriesBundle;
use Codefog\NewsCategoriesBundle\Model\NewsCategoryModel;
use Contao\CommentsBundle\ContaoCommentsBundle;
use Contao\CommentsModel;
use Contao\Config;
use Contao\ContentModel;
use Contao\CoreBundle\ContaoCoreBundle;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Dbafs;
use Contao\FilesModel;
use Contao\NewsArchiveModel;
use Contao\NewsModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\UserModel;
use Doctrine\DBAL\Connection;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Nyholm\Psr7\Uri;
use PHPHtmlParser\Dom\HtmlNode;
use PHPHtmlParser\Exceptions\ChildNotFoundException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Webmozart\PathUtil\Path;
use WordPressImportBundle\Event\ApiResponseBodyEvent;
use WordPressImportBundle\Event\ImportWordPressPostEvent;

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
     * Logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Contao root dir.
     *
     * @var string
     */
    protected $projectDir;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * Constructor for Importer service.
     *
     * @param Connection $db Database connection
     */
    public function __construct(Connection $db, ContaoFramework $framework, EventDispatcherInterface $eventDispatcher, LoggerInterface $logger, string $projectDir)
    {
        $this->db = $db;
        $this->framework = $framework;
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
        $this->projectDir = $projectDir;
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

        $event = $this->eventDispatcher->dispatch(new ApiResponseBodyEvent($json, $client, $endpoint));

        return json_decode($event->getBody());
    }

    /**
     * Imports a news item.
     */
    protected function importNews(Client $client, $objPost, NewsArchiveModel $objArchive): void
    {
        // only import posts
        if ('post' !== $objPost->type) {
            return;
        }

        // get the target folder
        $strTargetFolder = FilesModel::findOneByUuid($objArchive->wpImportFolder)->path;

        // Determine the boolean value
        $falseValue = version_compare(ContaoCoreBundle::getVersion(), '5', '<') ? '' : 0;

        // create new news
        $objNews = new NewsModel();
        $objNews->pid = $objArchive->id;
        $objNews->tstamp = time();
        $objNews->date = (new \DateTime($objPost->date_gmt, new \DateTimeZone('UTC')))->getTimestamp();
        $objNews->time = $objNews->date;
        $objNews->published = (('publish' === $objPost->status) ? '1' : '');
        $objNews->teaser = $this->processHtml($objPost->excerpt->rendered, $strTargetFolder, $objArchive);
        $objNews->headline = strip_tags($objPost->title->rendered);
        $objNews->source = 'default';
        $objNews->floating = 'above';
        $objNews->alias = $objPost->slug;
        $objNews->wpPostId = $objPost->id;
        $objNews->noComments = ('closed' !== $objPost->comment_status ? $falseValue : 1);
        $objNews->author = $objArchive->wpDefaultAuthor;

        if (null !== NewsModel::findOneByAlias($objNews->alias)) {
            $objNews->alias .= '-'.$objPost->id;
        }

        // save the news
        $objNews->save();

        try {
            // import the teaser image
            $this->importImage($client, $objPost, $objNews, $objArchive, $strTargetFolder);

            // import the detail text
            $this->importContent($objPost, $objNews, $objArchive, $strTargetFolder);

            // import the categories
            $this->importCategories($client, $objPost, $objNews, $objArchive);

            // import the authors
            $this->importAuthor($client, $objPost, $objNews, $objArchive);

            // import comments
            $this->importComments($client, $objPost, $objNews, $objArchive);

            // Dispatch event
            $this->eventDispatcher->dispatch(new ImportWordPressPostEvent($client, $objPost, $objNews));
        } catch (\Exception $e) {
            // Delete in case of error
            $objNews->delete();

            throw $e;
        }
    }

    /**
     * Downloads the featured_media of the post and adds as a teaser image.
     * If it does not exist, it takes the first image from the content.
     */
    protected function importImage(Client $client, $objPost, NewsModel $objNews, NewsArchiveModel $objArchive, $strTargetFolder): void
    {
        if (!empty($objPost->featured_media)) {
            try {
                $objMedia = $this->request($client, self::API_MEDIA.'/'.$objPost->featured_media);
            } catch (ClientException $e) {
                $this->logger->error('Could not fetch featured_media for Wordpress article "'.$objNews->headline.'": '.$e->getMessage());

                $objMedia = null;
            }

            if ($objMedia && 'image' === $objMedia->media_type) {
                // Download
                $objFile = $this->downloadFile($objMedia->source_url, $strTargetFolder, $objArchive->wpImportUrl);

                // Check if file exists
                if ($objFile) {
                    // Check meta-information
                    $meta = StringUtil::deserialize($objFile->meta, true);
                    $language = 'en';

                    if (null !== ($page = $objArchive->getRelated('jumpTo'))) {
                        /** @var PageModel $page */
                        $language = $page->loadDetails()->language;
                    }

                    if ($title = strip_tags($objMedia->title->rendered)) {
                        $meta[$language]['title'] = $title;
                    }

                    if ($caption = strip_tags($objMedia->caption->rendered)) {
                        $meta[$language]['caption'] = $caption;
                    }

                    if ($objMedia->alt_text) {
                        $meta[$language]['alt'] = $objMedia->alt_text;
                    }

                    if (!empty($meta)) {
                        $objFile->meta = $meta;
                        $objFile->save();
                    }

                    $objNews->addImage = '1';
                    $objNews->singleSRC = $objFile->uuid;
                    $objNews->save();

                    return;
                }
            }
        }

        // No explicit teaser image defined, take the first from the content
        $dom = new \PHPHtmlParser\Dom();
        $dom->load($objPost->content->rendered);

        // find the first image
        $img = $dom->find('img', 0);

        if (!$img) {
            return;
        }

        // download
        $objFile = $this->downloadFile($img->getAttribute('src'), $strTargetFolder, $objArchive->wpImportUrl);

        // check if file exists
        if ($objFile) {
            $objNews->addImage = '1';
            $objNews->singleSRC = $objFile->uuid;
            $objNews->save();
        }
    }

    /**
     * Imports the the rendered content of the Wordpress post as a single text
     * content element within the Contao news article.
     */
    protected function importContent($objPost, NewsModel $objNews, NewsArchiveModel $objArchive, $strTargetFolder): void
    {
        if (empty($objPost->content) || empty($objPost->content->rendered)) {
            return;
        }

        $objContent = new ContentModel();
        $objContent->ptable = NewsModel::getTable();
        $objContent->sorting = 128;
        $objContent->tstamp = time();
        $objContent->pid = $objNews->id;
        $objContent->type = 'text';
        $objContent->text = $this->processHtml($objPost->content->rendered, $strTargetFolder, $objArchive);
        $objContent->save();
    }

    /**
     * Downloads a file.
     *
     * @param string $strUrl
     * @param string $strTargetFolder
     * @param string $strBase
     */
    protected function downloadFile($strUrl, $strTargetFolder, $strBase): ?FilesModel
    {
        if (!$strUrl || !$strTargetFolder) {
            return null;
        }

        // decode the url

        // get the url info
        $objUrlinfo = (object) parse_url($strUrl);

        if (empty($objUrlinfo->path)) {
            return null;
        }

        // get the path info
        $objPathinfo = (object) pathinfo($objUrlinfo->path);

        // we only allow the download of files that have an extension
        if (empty($objPathinfo->extension)) {
            return null;
        }

        // determine the subfolder
        $strSubFolder = trim(str_replace('wp-content/uploads', '', $objPathinfo->dirname), '/');

        // determine the filename
        $strFileName = $objPathinfo->filename.'-'.substr(md5($strUrl), 0, 8).'.'.$objPathinfo->extension;

        // determine file paths
        $strFilePath = Path::join($strTargetFolder, $strSubFolder, $strFileName);
        $absoluteFilePath = Path::join($this->projectDir, $strFilePath);

        // check if file exists already
        if (file_exists($absoluteFilePath)) {
            return Dbafs::addResource($strFilePath);
        }

        // check if subdirectory exists
        if (!file_exists(Path::join($this->projectDir, $strTargetFolder, $strSubFolder))) {
            mkdir(Path::join($this->projectDir, $strTargetFolder, $strSubFolder), 0777, true);
        }

        // Prepend base if necessary
        if (0 !== stripos($strUrl, 'http')) {
            $strUrl = rtrim($strBase, '/').'/'.ltrim($strUrl, '/');
        }

        // download the file
        try {
            (new Client())->get($strUrl, ['sink' => $absoluteFilePath]);
        } catch (\Exception $e) {
            $this->logger->error('Could not download "'.$strUrl.'": '.$e->getMessage());

            return null;
        }

        if (file_exists($absoluteFilePath)) {
            return Dbafs::addResource($strFilePath);
        }

        return null;
    }

    /**
     * Processes the text and looks for any src and srcset attributes,
     * downloads the images and replaces them with {{file::*}} insert tags.
     *
     * @param string $strText
     *
     * @return string
     */
    protected function processHtml($strText, $strTargetFolder, NewsArchiveModel $archive)
    {
        // strip tags to certain allowed tags
        $strText = strip_tags($strText, Config::get('allowedTags'));

        // parse the text
        $dom = new \PHPHtmlParser\Dom();
        $dom->load($strText);

        // find all images
        $imgs = $dom->find('img');

        // determine language
        $language = 'en';

        if (null !== ($page = $archive->getRelated('jumpTo'))) {
            /** @var PageModel $page */
            $language = $page->loadDetails()->language;
        }

        // go through each image
        /** @var HtmlNode $img */
        foreach ($imgs as $img) {
            // check meta-information
            $meta = [];

            if ($alt = $img->getAttribute('alt')) {
                $meta[$language]['alt'] = $alt;
            }

            if ($title = $img->getAttribute('title')) {
                $meta[$language]['title'] = $title;
            }

            if ($caption = $this->getCaption($img)) {
                $meta[$language]['caption'] = $caption;
            }

            // check if image has src
            if ($img->getAttribute('src')) {
                // download the src
                if (null !== ($objFile = $this->downloadFile($img->getAttribute('src'), $strTargetFolder, $archive->wpImportUrl))) {
                    // set insert tags
                    $img->setAttribute('src', '{{file::'.StringUtil::binToUuid($objFile->uuid).'}}');

                    // save meta info
                    if (!empty($meta)) {
                        $originalMeta = StringUtil::deserialize($objFile->meta, true);
                        $originalMeta[$language] = array_merge($originalMeta[$language] ?? [], $meta[$language]);
                        $objFile->meta = $originalMeta;
                        $objFile->save();
                    }
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
                        if (null !== ($objFile = $this->downloadFile($arrSrcdesc[0], $strTargetFolder, $archive->wpImportUrl))) {
                            // set the new src
                            $arrSrcdesc[0] = '{{file::'.StringUtil::binToUuid($objFile->uuid).'}}';

                            // save meta info
                            if (!empty($meta)) {
                                $originalMeta = StringUtil::deserialize($objFile->meta, true);
                                $originalMeta[$language] = array_merge($originalMeta[$language] ?? [], $meta[$language]);
                                $objFile->meta = $originalMeta;
                                $objFile->save();
                            }
                        }
                    }

                    // set srcdesc again
                    $srcdesc = implode(' ', $arrSrcdesc);
                }

                // set srcset
                $img->setAttribute('srcset', implode(', ', $arrSrcset));
            }

            // check if surrounded by a link in which case that would be a lightbox
            if ('a' === $img->getParent()->getTag()->name() && ($imgUrl = $img->getParent()->getAttribute('href'))) {
                // download the image
                if (null !== ($objFile = $this->downloadFile($imgUrl, $strTargetFolder, $archive->wpImportUrl))) {
                    // set the new src
                    $img->getParent()->setAttribute('href', '{{file::'.StringUtil::binToUuid($objFile->uuid).'}}');

                    // mark it as lightbox
                    $img->getParent()->setAttribute('data-lightbox', 'true');
                }
            }
        }

        // find all links
        $links = $dom->find('a');
        $wpHost = (new Uri($archive->wpImportUrl))->getHost();

        // go through each link
        /** @var HtmlNode $link */
        foreach ($links as $link) {
            $href = $link->getAttribute('href');

            if (empty($href)) {
                continue;
            }

            $host = (new Uri($href))->getHost();

            // ignore links that are not on the same host
            if ($host !== $wpHost) {
                continue;
            }

            $ext = strtolower(pathinfo($href, \PATHINFO_EXTENSION));
            $validExts = explode(',', Config::get('allowedDownload'));

            // ignore unallowed download file extensions
            if (!\in_array($ext, $validExts, true)) {
                continue;
            }

            // download the linked file
            if (null !== ($file = $this->downloadFile($href, $strTargetFolder, $archive->wpImportUrl))) {
                $link->setAttribute('href', '{{file::'.StringUtil::binToUuid($file->uuid).'}}');
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
        if (!class_exists(CodefogNewsCategoriesBundle::class)) {
            return;
        }

        // process the categories
        $arrCategories = StringUtil::deserialize($objNews->categories, true);

        // go through each category
        foreach ($objPost->categories as $intCategoryId) {
            // import the category
            if (null !== ($objCategory = $this->importCategory($intCategoryId, $client, $objArchive))) {
                // check if news is not already assigned to category
                if (!$this->db->fetchAllAssociative('SELECT * FROM tl_news_categories WHERE category_id = ? AND news_id = ?', [$objCategory->id, $objNews->id])) {
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
            $objCategory->alias = $objWPCategory->slug;
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
    protected function importComments(Client $client, $objPost, NewsModel $objNews, NewsArchiveModel $archive): void
    {
        if (!$archive->wpImportComments) {
            return;
        }

        // only import comments, if the ContaoCommentsBundle is available
        if (!class_exists(ContaoCommentsBundle::class)) {
            return;
        }

        // retreive the comments for the post
        try {
            $arrComments = $this->request($client, self::API_COMMENTS, ['post' => $objPost->id]);
        } catch (ClientException $e) {
            $this->logger->error('Could not fetch comments for Wordpress article "'.$objNews->headline.'": '.$e->getMessage());

            return;
        }

        // go through each comment
        foreach ($arrComments as $objWPComment) {
            $objComment = new CommentsModel();
            $objComment->tstamp = time();
            $objComment->source = 'tl_news';
            $objComment->parent = $objNews->id;
            $objComment->date = (new \DateTime($objWPComment->date_gmt, new \DateTimeZone('UTC')))->getTimestamp();
            $objComment->name = $objWPComment->author_name;
            $objComment->website = $objWPComment->author_url;
            $objComment->comment = $objWPComment->content->rendered;
            $objComment->published = ('approved' === $objWPComment->status);
            $objComment->save();
        }
    }

    /**
     * Returns the caption for a given <img>.
     */
    private function getCaption(HtmlNode $img): ?string
    {
        if ('img' !== $img->getTag()) {
            return null;
        }

        $parent = $img->getParent();
        $count = 0;

        while ('figure' !== $parent->getTag() && $count < 3) {
            $parent = $parent->getParent();
            ++$count;
        }

        if ('figure' !== $parent->getTag()) {
            return null;
        }

        try {
            /** @var HtmlNode $caption */
            if ($caption = $parent->find('figcaption', 0)) {
                return $caption->text();
            }
        } catch (ChildNotFoundException $e) {
            // ignore
        }

        return null;
    }
}
