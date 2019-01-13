<?php

namespace Grav\Plugin\SocialFeed\Api;

use Grav\Plugin\SocialFeed\Model\Post;
use Instagram\Instagram;

final class InstagramApi extends SocialApi
{
    /**
     * @var string
     */
    protected $providerName = 'instagram';

    /**
     * @var array
     */
    private $config;

    /**
     * @param array $oAuthConfig
     */
    public function __construct($oAuthConfig)
    {
        $this->api = new Instagram();
        $this->api->setClientID($oAuthConfig['client_id']);
        $this->api->setAccessToken($oAuthConfig['access_token']);

        $this->config = $oAuthConfig;
    }

    /**
     * {@inherit}.
     */
    public function getUserPosts($username)
    {
        $user = $this->api->getCurrentUser($username);
        $media = $user->getMedia();

        return $media->getData();
    }

    /**
     * {@inherit}.
     */
    protected function getMappedPostObject($socialPost)
    {
        $post = new Post();
        $socialPost = $socialPost->getData();

        if (!isset($socialPost->caption->text)) {
            return false;
        }

        $post->setProvider($this->providerName);
        $post->setPostId($socialPost->id);
        $post->setAuthorUsername($socialPost->user->username);
        $post->setAuthorName($socialPost->user->full_name);
        $post->setAuthorFileUrl($socialPost->user->profile_picture);
        $post->setHeadline(strip_tags($socialPost->caption->text));

        $text = $this->getFormattedTextFromPost($socialPost);
        $post->setBody($text);
        $post->setFileUrl($socialPost->images->standard_resolution->url);
        $post->setLink($socialPost->link);

        $publishAt = new \DateTime();
        $publishAt->setTimestamp($socialPost->created_time);

        $post->setPublishedAt($publishAt);

        return $post;
    }

    /**
     * Get formated text from post.
     *
     * @param \stdClass $socialPost
     *
     * @return string
     */
    private function getFormattedTextFromPost($socialPost)
    {
        $text = $socialPost->caption->text;
        // Add href for links prefixed with ***:// (*** is most likely to be http(s) or ftp
        $text = preg_replace("#(^|[\n ])([\w]+?://[\w]+[^ \"\n\r\t< ]*)#", '\\1<a href="\\2" target="_blank">\\2</a>', $text);
        // Add href for links starting with www or ftp
        $text = preg_replace("#(^|[\n ])((www|ftp)\.[^ \"\t\n\r< ]*)#", '\\1<a href="http://\\2" target="_blank">\\2</a>', $text);

        return $text;
    }
}
