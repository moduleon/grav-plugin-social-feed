<?php

namespace Grav\Plugin\SocialFeed\Api;

use Grav\Plugin\SocialFeed\Model\Post;
use Facebook\Facebook;

final class FacebookApi extends SocialApi
{
    /**
     * @var string
     */
    protected $providerName = 'facebook';

    /**
     * @var string
     */
    protected $accessToken = '';

    /**
     * @param array $oAuthConfig
     */
    public function __construct($oAuthConfig)
    {
        $this->api = new Facebook([
            'app_id' => $oAuthConfig['app_id'],
            'app_secret' => $oAuthConfig['app_secret'],
            'default_graph_version' => 'v3.2',
            'default_access_token' => $oAuthConfig['app_id'].'|'.$oAuthConfig['app_secret'],
        ]);
    }

    /**
     * {@inherit}.
     */
    public function getUserPosts($feed)
    {
        //save user accesstoken if is set
        if(isset($feed['accesstoken']) && !empty($feed['accesstoken'])) {
            $this->accessToken = $feed['accesstoken'];
        }

        $parameters = '?fields=full_picture,from,message,id,permalink_url,created_time';
        $response = $this->requestGet('/'.$feed['username'].'/posts' . $parameters);
        return $response->getGraphEdge();
    }

    /**
     * {@inherit}.
     */
    protected function getMappedPostObject($socialPost)
    {

        $post = new Post();

        //body
        $message = $this->getFormattedTextFromPost($socialPost->getField('message'));
        if (!isset($message)) {
            return false;
        }
        $post->setBody($message);

        //creator username and image
        $from = $socialPost->getField('from');
        $rawUserDetails = $this->requestGet('/' . $from['id'] . '?fields=username,picture');
        $userDetails = $rawUserDetails->getGraphNode();

        if (empty($userDetails)) {
            return false;
        }

        $post->setAuthorName($from['name']);
        $post->setAuthorUsername($userDetails->getField('username'));
        $post->setAuthorFileUrl($userDetails->getField('picture')['url']);

        //post image
        $fullPicture = $socialPost->getField('full_picture');
        if (isset($fullPicture) && !empty($fullPicture)) {
            $file = $socialPost->getField('full_picture');
            $post->setFileUrl($file);
        }

        //other params
        $post->setHeadline(strip_tags($socialPost->getField('message')));
        $post->setPostId($socialPost->getField('id'));
        $post->setProvider($this->providerName);
        $post->setLink($socialPost->getField('permalink_url'));
        $post->setPublishedAt($socialPost->getField('created_time'));

        return $post;
    }

    /**
     * Format text
     *
     * @param string $text
     *
     * @return string
     */
    private function getFormattedTextFromPost($text)
    {
        // Add href for links prefixed with ***:// (*** is most likely to be http(s) or ftp
        $text = preg_replace("#(^|[\n ])([\w]+?://[\w]+[^ \"\n\r\t< ]*)#", '\\1<a href="\\2" target="_blank">\\2</a>', $text);
        // Add href for links starting with www or ftp
        $text = preg_replace("#(^|[\n ])((www|ftp)\.[^ \"\t\n\r< ]*)#", '\\1<a href="http://\\2" target="_blank">\\2</a>', $text);
        // Add link to hashtags
        $text = preg_replace("/#(\w+)/", '<a href="https://www.facebook.com/hashtag/\\1" target="_blank">#\\1</a>', $text);

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
            // Returns a `FacebookFacebookResponse` object
            $response = $this->api->get(
                $url,
                $this->accessToken
            );
        } catch(FacebookExceptionsFacebookResponseException $e) {
            echo 'Graph returned an error: ' . $e->getMessage();
            exit;
        } catch(FacebookExceptionsFacebookSDKException $e) {
            echo 'Facebook SDK returned an error: ' . $e->getMessage();
            exit;
        }
        return $response;
    }
}
