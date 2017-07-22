<?php

namespace Grav\Plugin\SocialFeed\Api;

use Grav\Plugin\SocialFeed\Model\Post;

final class TwitterApi extends SocialApi
{
    /**
     * @var string
     */
    protected $providerName = 'twitter';

    /**
     * @param array $oAuthConfig
     */
    public function __construct($oAuthConfig)
    {
        $this->api = new \tmhOAuth($oAuthConfig);
    }

    /**
     * {@inherit}.
     */
    public function getUserPosts($username)
    {
        $data = $this->requestGet(
            'statuses/user_timeline',
            array(
                'screen_name' => $username,
                'exclude_replies' => true,
                'include_rts' => false,
                'count' => 20,
            )
        );

        return $data;
    }

    /**
     * {@inherit}.
     */
    protected function getMappedPostObject($socialPost)
    {
        $post = new Post();

        $post->setProvider($this->providerName);
        $post->setPostId($socialPost['id_str']);

        $post->setAuthorUsername($socialPost['user']['screen_name']);
        $post->setAuthorName($socialPost['user']['name']);
        $post->setAuthorFileUrl($socialPost['user']['profile_image_url']);

        $text = $this->getFormattedTextFromPost($socialPost);
        $post->setHeadline(strip_tags($socialPost['text']));
        $post->setBody($text);

        if (isset($socialPost['entities']['media'][0])) {
            $post->setFileUrl($socialPost['entities']['media'][0]['media_url']);
        }

        $post->setLink('https://twitter.com/'.$socialPost['user']['screen_name'].'/status/'.$socialPost['id_str']);

        $post->setPublishedAt(new \DateTime($socialPost['created_at']));

        return $post;
    }

    /**
     * Get formated text from post.
     *
     * @param array $socialPost
     *
     * @return string
     */
    private function getFormattedTextFromPost(array $socialPost)
    {
        $text = $socialPost['text'];

        if (isset($socialPost['entities']['urls']) && !empty($socialPost['entities']['urls'])) {
            foreach ($socialPost['entities']['urls'] as $url) {
                $text = str_replace($url['url'], $url['expanded_url'], $text);
            }
        }

        // Replace &nbsp; with a normal space
        $text = str_replace(chr('0xC2').chr('0xA0'), ' ', $text);
        // Add href for links prefixed with ***:// (*** is most likely to be http(s) or ftp
        $text = preg_replace("#(^|[\n ])([\w]+?://[\w]+[^ \"\n\r\t< ]*)#", '\\1<a href="\\2" target="_blank">\\2</a>', $text);
        // Add href for links starting with www or ftp
        $text = preg_replace("#(^|[\n ])((www|ftp)\.[^ \"\t\n\r< ]*)#", '\\1<a href="http://\\2" target="_blank">\\2</a>', $text);
        // Add links to twitter usernames when mentions are used
        $text = preg_replace("/@(\w+)/", '<a href="http://www.twitter.com/\\1" target="_blank">@\\1</a>', $text);
        // Add link to hashtag pages
        $text = preg_replace("/#(\w+)/", '<a href="http://www.twitter.com/search?q=%23\\1" target="_blank">#\\1</a>', $text);

        return $text;
    }

    /**
     * Send a GET request.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @return array
     *
     * @throws \Exception
     */
    private function requestGet($method, $parameters = array())
    {
        $responseCode = $this->api->request(
            'GET',
            $this->api->url('1.1/'.$method),
            $parameters
        );

        $responseData = json_decode($this->api->response['response'], true);
        if (200 === intval($responseCode)) {
            return $responseData;
        } else {
            throw new \Exception($responseData['errors'][0]['message'], $responseData['errors'][0]['code']);
        }
    }
}
