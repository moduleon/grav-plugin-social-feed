<?php

namespace Grav\Plugin\SocialFeed\Api;

use Grav\Common\Grav;
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
     *  get ssl config
     */
    public function __construct()
    {
        $grav = Grav::instance();
        $config = $grav['config']->get('plugins.social-feed');
        $this->config['enablessl'] = $config['enablessl'];
        $this->config['certpath'] = $config['certpath'];
    }

    /**
     * {@inherit}.
     */
    public function getUserPosts($feed)
    {

        //save user accesstoken if is set
        if(isset($feed['userid']) && !empty($feed['userid'])) {
            $this->config['userid'] = $feed['userid'];
            $this->config['access_token'] = "&access_token=".$feed['access_token'];
            $this->config['avatar'] = array_key_first($feed['avatar']);

            $fields = '?fields=caption,id,media_type,media_url,permalink,thumbnail_url,timestamp,username';
            $response = $this->requestGet('https://graph.instagram.com/'.$this->config['userid'].'/media' . $fields);
            return $response['data'];
        }


    }

    /**
     * {@inherit}.
     */
    protected function getMappedPostObject($socialPost)
    {
        $post = new Post();

        $post->setProvider($this->providerName);
        $post->setPostId($socialPost['id']);
        $post->setAuthorUsername($socialPost['username']);

        /*
         * For now it is not possible to get name and avatar by instagram api
         * @todo fetch user media as soon as it is possible by instagram api
         */
        $post->setAuthorFileUrl($this->config['avatar']);
        //$fields = '?fields=account_type,id,media_count,username';
        //$userData = $this->requestGet('https://graph.instagram.com/' . $this->config['userid'] . $fields);
        //$post->setAuthorName($userData['name']);
        //$post->setAuthorFileUrl($userData['avatar']);

        $post->setHeadline(strip_tags($socialPost['caption']));

        $text = $this->getFormattedTextFromPost($socialPost);
        $post->setBody($text);
        $post->setFileUrl($socialPost['media_url']);
        $post->setLink($socialPost['permalink']);

        $publishAt = new \DateTime($socialPost['timestamp']);
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
        $text = $socialPost['caption'];
        // Add href for links prefixed with ***:// (*** is most likely to be http(s) or ftp
        $text = preg_replace("#(^|[\n ])([\w]+?://[\w]+[^ \"\n\r\t< ]*)#", '\\1<a href="\\2" target="_blank">\\2</a>', $text);
        // Add href for links starting with www or ftp
        $text = preg_replace("#(^|[\n ])((www|ftp)\.[^ \"\t\n\r< ]*)#", '\\1<a href="http://\\2" target="_blank">\\2</a>', $text);

        return $text;
    }

    /**
     * Send a GET request.
     *
     * @param string $url
     *
     * @return getGraphNode
     */
    private function requestGet($url)
    {
        $arrContextOptions = array();

        if($this->config['enablessl'] === false) {
            $arrContextOptions['ssl']['verify_peer'] = false;
            $arrContextOptions['ssl']['verify_peer_name'] = false;
        }

        if(isset($this->config['certpath']) && !empty($this->config['certpath'])) {
            $arrContextOptions['ssl']['cafile'] = $this->config['certpath'];
        }

        try {
            $response = file_get_contents($url . $this->config['access_token'], false, stream_context_create($arrContextOptions));
        } catch (Exception $e) {
            echo $e->getMessage();
            exit;
        }

        if($response == false) {
            echo "Something went wrong by getting the data of " . $this->providerName . " user: " . $this->config['userid'];
            exit;
        }

        return json_decode($response, true);
    }
}
