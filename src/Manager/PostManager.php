<?php

namespace Grav\Plugin\SocialFeed\Manager;

use Grav\Common\Grav;
use Grav\Common\File\CompiledYamlFile;
use Grav\Plugin\SocialFeed\Model\Post;
use RocketTheme\Toolbox\Event\Event;

/**
 * PostManager handles CRUD actions on posts.
 */
class PostManager
{
    /**
     * @var Grav
     */
    protected $grav;

    public function __construct($cli = false)
    {
        $this->grav = Grav::instance();
        if ($cli) {
            $this->grav->fireEvent('onPluginsInitialized');
        }
    }

    /**
     * Get posts according to given parameters.
     *
     * @param array $params
     *
     * @return array
     */
    public function getPosts(array $params = array())
    {
        $file = $this->getContentFile();
        // Filter posts
        $posts = array();
        foreach ($file->content() as $post) {
            if (isset($params['providers'])) {
                if (!in_array($post['provider'], $params['providers'])) {
                    continue;
                }
            }
            if (isset($params['usernames'])) {
                if (!in_array($post['authorUsername'], $params['usernames'])) {
                    continue;
                }
            }
            $posts[] = $post;
        }
        // Sort posts
        $orderBy = isset($params['order_by']) ? (string) $params['order_by'] : 'publishedAt';
        $orderDir = isset($params['order_dir']) ? (string) $params['order_dir'] : SORT_DESC;
        usort($posts, function ($a, $b) use ($orderBy, $orderDir) {
            if ($a[$orderBy] === $b[$orderBy]) {
                return 0;
            } else {
                $result = $a[$orderBy] < $b[$orderBy] ? -1 : 1;
                if (SORT_DESC === $orderDir) {
                    $result *= -1;
                }

                return $result;
            }
        });
        // Paginate
        $total = count($posts);
        $limit = isset($params['limit']) ? (int) $params['limit'] : 10;
        $page = isset($params['page']) ? (int) $params['page'] : 1;
        $offset = ($page * $limit) - $limit;
        $posts = array_splice($posts, $offset, $limit);
        // Absolute url
        if ($this->grav['config']->get('system.absolute_urls')) {
            $baseUrl = $this->grav['pages']->baseUrl();
            foreach ($posts as $key => $post) {
                $posts[$key]['fileUrl'] = $post['fileUrl'] ? $baseUrl.$post['fileUrl'] : null;
                $posts[$key]['authorFileUrl'] = $post['authorFileUrl'] ? $baseUrl.$post['authorFileUrl'] : null;
            }
        }

        return array(
            'total' => $total,
            'limit' => $limit,
            'page' => $page,
            'posts' => $posts,
        );
    }

    /**
     * Save posts locally.
     *
     * @param array $posts
     */
    public function savePosts(array $posts)
    {
        $file = $this->getContentFile();
        $content = $file->content();
        $updated = false;
        /** @var Post $post */
        foreach ($posts as $post) {
            if (!isset($content[$post->getProvider().'_'.$post->getPostId()])) {
                $updated = true;
                $arrayPost = [
                    'provider' => $post->getProvider(),
                    'postId' => $post->getPostId(),
                    'authorUsername' => $post->getAuthorUsername(),
                    'authorName' => $post->getAuthorName(),
                    'authorFileUrl' => $post->getAuthorFileUrl(),
                    'headline' => $post->getHeadline(),
                    'body' => $post->getBody(),
                    'fileUrl' => $post->getFileUrl(),
                    'link' => $post->getLink(),
                    'publishedAt' => $post->getPublishedAt()->format('Y-m-d H:i:s'),
                ];
                $event = new Event(['post' => $arrayPost]);
                $this->grav->fireEvent('onSocialPostBeforeSave', $event);
                $arrayPost = $event->offsetGet('post');
                $content[$post->getProvider().'_'.$post->getPostId()] = $arrayPost;
            }
        }

        if ($updated) {
            $file->content($content);
            $file->save();
        }
    }

    /**
     * Store post attachments.
     *
     * @param Post $post
     */
    public function storeAttachments(Post $post)
    {
        $locator = $this->grav['locator'];
        $uploadDir = $locator->findResource('user://media', true, true);
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir);
        }

        // Author picture - Updated every 15 min maximum.
        if ($post->getAuthorFileUrl()) {
            $basename = $post->getProvider().'_'.$post->getAuthorUsername();
            $files = glob($uploadDir.'/'.$basename.'.*');
            if (0 === count($files) || 900 > (time() - filemtime($files[0]))) {
                $filename = $this->downloadFile($post->getAuthorFileUrl(), $basename, $uploadDir);
                if ($filename) {
                    $post->setAuthorFileUrl($this->getMediaUrl().'/'.$filename);
                } else {
                    $post->setAuthorFileUrl(null);
                }
            }
        }

        // Post file - Downloaded if not found locally.
        if ($post->getFileUrl()) {
            $basename = $post->getProvider().'_'.$post->getPostId();
            $files = glob($uploadDir.'/'.$basename.'.*');
            if (0 === count($files)) {
                $filename = $this->downloadFile($post->getFileUrl(), $basename, $uploadDir);
                if ($filename) {
                    $post->setFileUrl($this->getMediaUrl().'/'.$filename);
                } else {
                    $post->setFileUrl(null);
                }
            }
        }
    }

    /**
     * Download a file.
     *
     * @param string $url      is the url where file is currently stored
     * @param string $basename is the base name to give to the file without the extension
     * @param string $dir      is the absolute path where to store the file
     *
     * @return string|null
     */
    private function downloadFile($url, $basename, $dir)
    {
        $fileContents = @file_get_contents($url);
        if (!$fileContents || strstr($fileContents, '<!DOCTYPE html>')) {
            return;
        }
        $storageFile = tempnam(sys_get_temp_dir(), 'SocialFeed');
        file_put_contents($storageFile, $fileContents);
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $storageFile);
        $ext = strtolower(explode('/', $mime)[1]);
        if (true === in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
            $filename = $basename.'.'.$ext;
            $destination = $dir.'/'.$filename;
            copy($storageFile, $destination);
            unlink($storageFile);

            return $filename;
        }
    }

    /**
     * Get media folder public url.
     *
     * @return string
     */
    private function getMediaUrl()
    {
        return '/user/media';
    }

    /**
     * Get content file.
     *
     * @return CompiledYamlFile
     */
    private function getContentFile()
    {
        $locator = $this->grav['locator'];
        $filename = $locator->findResource('user://data/social-feed.yaml', true, true);

        return CompiledYamlFile::instance($filename);
    }
}
