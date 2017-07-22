<?php

namespace Grav\Plugin\SocialFeed\Api;

use Facebook\FacebookRequest;
use Facebook\FacebookSession;
use Facebook\Entities\AccessToken;
use Facebook\GraphObject;
use Grav\Plugin\SocialFeed\Model\Post;

final class FacebookApi extends SocialApi
{
    /**
     * @var string
     */
    protected $providerName = 'facebook';

    /**
     * @param array $oAuthConfig
     */
    public function __construct($oAuthConfig)
    {
        FacebookSession::setDefaultApplication($oAuthConfig['app_id'], $oAuthConfig['app_secret']);

        $accessToken = AccessToken::requestAccessToken(array('grant_type' => 'client_credentials'));
        $this->api = new FacebookSession($accessToken);
    }

    /**
     * {@inherit}.
     */
    public function getUserPosts($username)
    {
        try {
            $parameters = array('fields' => 'message,link,from,full_picture,created_time,object_id');
            $data = $this->requestGet('/'.$username.'/posts', $parameters);
        } catch (\Exception $ex) {
            echo $ex->getMessage();

            return array();
        }

        return $data->asArray()['data'];
    }

    /**
     * {@inherit}.
     */
    protected function getMappedPostObject($socialPost)
    {
        $post = new Post();

        if (!isset($socialPost->message)) {
            return false;
        }

        $post->setProvider($this->providerName);
        $post->setPostId($socialPost->id);

        $parameters = array('fields' => 'username');
        $rawUserDetails = $this->requestGet('/'.$socialPost->from->id, $parameters);

        $userDetails = $rawUserDetails->asArray();

        if (empty($userDetails)) {
            return false;
        }

        $post->setAuthorUsername($userDetails['username']);
        $post->setAuthorName($socialPost->from->name);
        $post->setAuthorFileUrl('https://graph.facebook.com/'.$socialPost->from->id.'/picture');
        $post->setHeadline(strip_tags($socialPost->message));

        $message = $this->getFormattedTextFromPost($socialPost);
        $post->setBody($message);

        if (isset($socialPost->full_picture) && !empty($socialPost->full_picture)) {
            $file = $socialPost->full_picture;

            // A picture is set, use the original url as a backup
            $post->setFileUrl($file);

            // If there is an object_id, then the original file may be available, so check for that one
            if (isset($socialPost->object_id)) {
                $rawImageDetails = $this->requestGet('/'.$socialPost->object_id, array('fields' => 'images'));
                $imageDetails = $rawImageDetails->asArray();

                if (isset($imageDetails['images'][0]->source)) {
                    $post->setFileUrl($imageDetails['images'][0]->source);
                }
            } else {
                // Check if it is an external image, if so, use the original one.
                $pictureUrlData = parse_url($socialPost->full_picture);
                if (1 === preg_match('#^(fb)?external#', $pictureUrlData['host'])) {
                    parse_str($pictureUrlData['query'], $pictureUrlQueryData);
                    if (isset($pictureUrlQueryData['url'])) {
                        $post->setFileUrl($pictureUrlQueryData['url']);
                    }
                }
            }
        }

        $post->setLink('https://www.facebook.com/'.$socialPost->id);
        $post->setPublishedAt(new \DateTime($socialPost->created_time));

        return $post;
    }

    /**
     * Get formated text from post.
     *
     * @param \stdClass $socialPost
     *
     * @return string
     */
    private function getFormattedTextFromPost(\stdClass $socialPost)
    {
        $text = $socialPost->message;
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
     * @param string $method
     * @param array  $parameters
     *
     * @return GraphObject
     */
    private function requestGet($method, $parameters = array()): GraphObject
    {
        $response = (new FacebookRequest($this->api, 'GET', $method, $parameters, 'v2.4'))->execute();

        return $response->getGraphObject();
    }
}
