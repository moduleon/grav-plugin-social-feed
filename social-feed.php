<?php

namespace Grav\Plugin;

use Grav\Common\Plugin;
use RocketTheme\Toolbox\Event\Event;
use Grav\Plugin\Twig\PostExtension;
use Grav\Plugin\SocialFeed\Manager\PostManager;

/**
 * Class SocialFeedPlugin.
 */
class SocialFeedPlugin extends Plugin
{
    /**
     * @return array
     *
     * The getSubscribedEvents() gives the core a list of events
     *     that the plugin wants to listen to. The key of each
     *     array section is the event that the plugin listens to
     *     and the value (in the form of an array) contains the
     *     callable (or function) as well as the priority. The
     *     higher the number the higher the priority.
     */
    public static function getSubscribedEvents()
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0],
        ];
    }

    /**
     * Initialize the plugin.
     */
    public function onPluginsInitialized()
    {
        // Don't proceed if we are in the admin plugin
        if ($this->isAdmin()) {
            return;
        }

        $uri = $this->grav['uri'];
        if (false !== strpos($uri->path(), '/social-posts')) {
            return $this->getPosts();
        } else {
            $this->enable([
                'onTwigExtensions' => ['onTwigExtensions', 0],
            ]);
        }
    }

    /**
     * Return posts as json.
     *
     * @return Json
     */
    public function getPosts()
    {
        $manager = new PostManager();
        header('Content-Type: application/json');
        echo json_encode($manager->getPosts($this->grav['uri']->query(null, true)));
        die;
    }

    /**
     * Add Twig Extensions.
     */
    public function onTwigExtensions()
    {
        $this->grav['twig']->twig->addExtension(new PostExtension());
    }
}
