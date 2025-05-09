<?php

namespace Grav\Plugin\Console;

use Grav\Common\Grav;
use Grav\Common\File\CompiledYamlFile;
use Grav\Console\ConsoleCommand;
use GuzzleHttp\Client;
use Symfony\Component\Console\Input\InputArgument;

class RefreshAllTokensCommand extends ConsoleCommand
{
    protected static $grav;

    public function __construct($name = null)
    {
        parent::__construct($name);
        self::$grav = Grav::instance();
    }

    /**
     * {@inherit}
     */
    protected function configure()
    {
        $this
            ->setName('refresh:all-tokens')
            ->setDescription('Attempts to refresh access tokens for all supported networks (Facebook, Instagram, Twitter).')
            ->addOption(
                'app_id',
                'id',
                InputArgument::OPTIONAL,
                'The App ID'
            )
            ->addOption(
                'app_secret',
                'secret',
                InputArgument::OPTIONAL,
                'The App Secret'
            );

        ;
    }

    /**
     * {@inherit}
     */
    protected function serve()
    {
        require_once __DIR__.'/../vendor/autoload.php';

        $config = self::$grav['config']->get('plugins.social-feed');
        $config['app_id'] = $this->input->getOption('app_id');
        $config['app_secret'] = $this->input->getOption('app_secret');

        // Liste von unterstützten Netzwerken
        $networks = ['facebook', 'instagram', 'twitter'];

        foreach ($networks as $network) {
            $this->output->writeln("Attempting to refresh token for: <yellow>$network</yellow>");

            switch ($network) {
                case 'facebook':
                    foreach ($config['facebook_feeds'] as $feed) {
                        $this->refreshFacebookToken($feed, $config);
                    }
                    break;

                case 'instagram':
                    foreach ($config['instagram_feeds'] as $feed) {
                        $this->refreshInstagramToken($feed, $config);
                    }
                    break;

                case 'twitter':
                    $this->refreshTwitterToken($config); // Placeholder: Implementieren, wenn notwendig
                    break;

                default:
                    $this->error("Unsupported network: $network");
            }
        }

        $this->success('All token refresh attempts completed.');
    }

    private function refreshFacebookToken($feed, $config)
    {
        if (!isset($feed['accesstoken']) || !isset($config['app_id']) || !isset($config['app_secret'])) {
            $this->error('Facebook configuration is incomplete.');
            return;
        }

        $longLivedToken = $feed['accesstoken'];
        $clientId = $config['app_id'];
        $clientSecret = $config['app_secret'];

        // Facebook-Token-Aktualisierung
        $client = new Client();
        try {
            $response = $client->request('GET', 'https://graph.facebook.com/v17.0/oauth/access_token', [
                'query' => [
                    'grant_type' => 'fb_exchange_token',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'fb_exchange_token' => $longLivedToken,
                ],
            ]);

            $body = json_decode($response->getBody(), true);
            if (isset($body['access_token'])) {
                $this->updateConfig('facebook', $feed, $body['access_token']);
                $this->success("Facebook token has been refreshed.");
            } else {
                $this->error("Failed to refresh Facebook token.");
            }
        } catch (\Exception $e) {
            $this->error("Error refreshing Facebook token: " . $e->getMessage());
        }
    }

    private function refreshInstagramToken($feed, $config)
    {
        if (!isset($config['instagram_access_token'])) {
            $this->error('Instagram configuration is incomplete.');
            return;
        }

        $accessToken = $config['instagram_access_token'];

        // Instagram-Token-Aktualisierung
        $client = new Client();
        try {
            $response = $client->request('GET', 'https://graph.instagram.com/refresh_access_token', [
                'query' => [
                    'grant_type' => 'ig_refresh_token',
                    'access_token' => $accessToken,
                ],
            ]);

            $body = json_decode($response->getBody(), true);
            if (isset($body['access_token'])) {
                $this->updateConfig('instagram_access_token', $body['access_token']);
                $this->success("Instagram token has been refreshed.");
            } else {
                $this->error("Failed to refresh Instagram token.");
            }
        } catch (\Exception $e) {
            $this->error("Error refreshing Instagram token: " . $e->getMessage());
        }
    }

    private function refreshTwitterToken($config)
    {
        // Twitter erlaubt keine unmittelbare Verlängerung von Tokens wie Facebook oder Instagram.
        $this->output->writeln('<yellow>Twitter token refresh is not implemented (requires application-specific workflow).</yellow>');
    }

    private function updateConfig($network, $feed, $value)
    {
        $configFile = CompiledYamlFile::instance('user/config/plugins/social-feed.yaml');
        $configData = $configFile->content();

        if ($network === 'facebook') {
            $userKey = 'username';
            $networkKey = 'facebook_feeds';
            $accessTokenKey = 'accesstoken';
        } else if ($network === 'instagram') {
            $userKey = 'userid';
            $networkKey = 'instagram_feeds';
            $accessTokenKey = 'access_token';
        } else {
            return;
        }

        foreach ($configData[$networkKey] as &$configFeed) {
            if ($configFeed[$userKey] === $feed[$userKey]) {
                $oldToken = $configFeed[$accessTokenKey];
                $configFeed[$accessTokenKey] = $value;
                break;
            }
        }

        if (isset($oldToken) && !empty($oldToken)) {
            foreach ($configData['facebook_feeds'] as &$configFeed) {
                if ($configFeed['accesstoken'] === $oldToken) {
                    $configFeed['accesstoken'] = $value;
                    break;
                }
            }

            foreach ($configData['instagram_feeds'] as &$configFeed) {
                if ($configFeed['access_token'] === $oldToken) {
                    $configFeed['access_token'] = $value;
                    break;
                }
            }
        }

        $configFile->save($configData);
        $configFile->free();
    }

    /**
     * Notify error.
     *
     * @param string $message
     */
    protected function error($message)
    {
        self::$grav['log']->error($message);
        $this->output->writeln('<red>' . $message . '</red>');
    }

    /**
     * Notify success.
     *
     * @param string $message
     */
    protected function success($message)
    {
        $this->output->writeln('<green>' . $message . '</green>');
    }
}
