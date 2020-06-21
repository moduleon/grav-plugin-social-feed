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
     * @param array $oAuthConfig
     */
    public function __construct($oAuthConfig)
    {
    }

    /**
     * {@inherit}.
     */
    public function getUserPosts($feed)
    {
        //save user accesstoken if is set
        if(isset($feed['userid']) && !empty($feed['access_token'])) {
            $this->config['userid'] = $feed['userid'];
            $this->config['access_token'] = "&access_token=".$feed['access_token'];

            $fields = '?fields=caption,id,media_type,media_url,permalink,thumbnail_url,timestamp,username';
            $response = $this->requestGet('https://graph.instagram.com/'.$this->config['userid'].'/media' . $fields);
            $response = json_decode($response, true);
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
        //$fields = '?fields=account_type,id,media_count,username';
        //$userData = $this->requestGet('https://graph.instagram.com/' . $this->config['userid'] . $fields);
        //$post->setAuthorName($userData['name']);
        //$post->setAuthorFileUrl($userData['avatar]);

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
        try {
            $response = @file_get_contents($url . $this->config['access_token']);
        }
        catch (Exception $e) {
            echo $e->getMessage();
            exit;
        }

        if($response == false) {
            echo "Something went wrong by getting the data of user: " . $this->config['userid'];
            exit;
        }

        return $response;
    }
}
