<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Maatoo\Maatoo\Auth;

interface AuthInterface
{
    /**
     * Check if current authorization is still valid.
     *
     * @return bool
     */
    public function isAuthorized();

    /**
     * Make a request to server using the supported auth method.
     *
     * @param string $url
     * @param string $method
     *
     * @return array
     */
    public function makeRequest($url, array $parameters = [], $method = 'GET', array $settings = []);
}