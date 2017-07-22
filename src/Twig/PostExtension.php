<?php

namespace Grav\Plugin\SocialFeed\Twig;

use Grav\Plugin\SocialFeed\Manager\PostManager;

/**
 * PostExtension allows to fetch posts from twig templates.
 */
class PostExtension extends \Twig_Extension
{
    /**
     * Returns extension name.
     *
     * @return string
     */
    public function getName()
    {
        return 'PostExtension';
    }

    /**
     * Adding filters.
     *
     * @return array
     */
    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('socialPosts', [$this, 'getSocialPosts']),
        ];
    }

    /**
     * Get posts according to given parameters.
     *
     * @param  array  $params
     *
     * @return array
     */
    public function getSocialPosts(array $params = array())
    {
        $manager = new PostManager();

        return $manager->getPosts($params);
    }
}
