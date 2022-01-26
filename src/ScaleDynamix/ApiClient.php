<?php

/*******************************************************************************
*                                                                              *
*   Asinius\ScaleDynamix\ApiClient                                             *
*                                                                              *
*   Client for the Scale Dynamix API (https://scaledynamix.github.io/api/)     *
*                                                                              *
*   LICENSE                                                                    *
*                                                                              *
*   Copyright (c) 2022 Rob Sheldon <rob@robsheldon.com>                        *
*                                                                              *
*   Permission is hereby granted, free of charge, to any person obtaining a    *
*   copy of this software and associated documentation files (the "Software"), *
*   to deal in the Software without restriction, including without limitation  *
*   the rights to use, copy, modify, merge, publish, distribute, sublicense,   *
*   and/or sell copies of the Software, and to permit persons to whom the      *
*   Software is furnished to do so, subject to the following conditions:       *
*                                                                              *
*   The above copyright notice and this permission notice shall be included    *
*   in all copies or substantial portions of the Software.                     *
*                                                                              *
*   THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS    *
*   OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF                 *
*   MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.     *
*   IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY       *
*   CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,       *
*   TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE          *
*   SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.                     *
*                                                                              *
*   https://opensource.org/licenses/MIT                                        *
*                                                                              *
*******************************************************************************/

namespace Asinius\ScaleDynamix;

use Exception, RuntimeException;


/*******************************************************************************
*                                                                              *
*   Constants                                                                  *
*                                                                              *
*******************************************************************************/

const CMS_WORPDRESS                 = 1;
const CMS_WP_MULTISITE_SUBDOMAINS   = 2;
const CMS_WP_MULTISITE_DIRECTORIES  = 3;
const CMS_WOOCOMMERCE               = 4;
const CMS_PHP_HTML                  = 5;
const CMS_CLONE                     = 9;



/*******************************************************************************
*                                                                              *
*   \Asinius\ScaleDynamix\ApiClient                                            *
*                                                                              *
*******************************************************************************/

class ApiClient
{

    protected static $_http_client  = null;
    protected static $_api_version  = 'v1';
    protected static $_api_endpoint = 'https://api.scaledynamix.com/';
    protected static $_api_key      = '';
    protected static $_cache        = ['stacks' => [], 'sites' => []];


    /**
     * Execute an API request and handle some common errors.
     *
     * @param   string      $endpoint
     * @param   string      $method
     * @param   ?array      $parameters
     *
     * @throws  RuntimeException
     *
     * @return  mixed
     */
    protected static function _exec ($endpoint, $method = 'GET', $parameters = false)
    {
        //  TODO: It would be nice to do some automated caching here.
        $url = sprintf('%s/%s/%s', static::$_api_endpoint, static::$_api_version, $endpoint);
        if ( $method === 'GET' ) {
            $response = static::$_http_client->get($url, $parameters, ['Key' => static::$_api_key]);
        }
        else if ( $method === 'POST' ) {
            $response = static::$_http_client->post($url, $parameters, ['Key' => static::$_api_key]);
        }
        else {
            throw new RuntimeException("Unsupported API request type: $method", EUNDEF);
        }
        if ( $response->code === 401 ) {
            throw new RuntimeException("You are not authorized to $method $url", 401);
        }
        $response_body = $response->body;
        if ( ! isset($response_body['success']) ) {
            throw new RuntimeException("Unexpected API response to $method $url", EUNDEF);
        }
        if ( $response_body['success'] !== true ) {
            throw new RuntimeException("API request failed for $method $url; received [" . print_r($response_body['true'], true) . "] for 'success' flag");
        }
        return $response;
    }


    /**
     * Validate a site ID. Must be either an integer > 0, or a numeric string
     * that represents an integer > 0.
     *
     * @param   mixed       $site_id
     *
     * @internal
     *
     * @return boolean
     */
    protected static function _is_valid_id ($site_id)
    {
        if ( is_int($site_id) ) {
            return ($site_id > 0);
        }
        if ( is_string($site_id) ) {
            return (preg_match('/^[0-9]+$/', $site_id) === 1 && ((int) $site_id > 0));
        }
        return false;
    }


    /**
     * Test an API key provided by the application. If the request succeeds,
     * the application is considered "signed in".
     * You can get your API key at https://platform.scaledynamix.com/settings
     *
     * @param   array       $login_parameters
     *
     * @throws  RuntimeException
     *
     * @return  void
     */
    public static function login ($login_parameters)
    {
        if ( static::$_http_client !== NULL ) {
            //  Already successfully signed in.
            return;
        }
        if ( ! array_key_exists('api_key', $login_parameters) ) {
            throw new RuntimeException('The Scale Dynamix API requires an API key. This must be passed as an "api_key" value to login()');
        }
        static::$_api_key = $login_parameters['api_key'];
        static::$_http_client = new \Asinius\HTTP\Client();
        static::$_http_client->setopt(CURLOPT_SSLVERSION, CURL_SSLVERSION_MAX_TLSv1_3);
        try {
            //  The API doesn't have an official "login" endpoint; requesting
            //  the list of available providers should be a low-overhead way
            //  of verifiying the API key.
            static::_exec('providers');
        }
        catch (Exception $e) {
            static::$_http_client = NULL;
            if ( $e->getCode() === 401 ) {
                throw new RuntimeException('API login failed');
            }
            throw new RuntimeException($e->getMessage(), $e->getCode());
        }
    }


    /**
     * Change the URI used to communicate with the Scale Dynamix API.
     * This will force a logout() on an active connection.
     *
     * @param   string      $uri
     *
     * @return  void
     */
    public static function set_api_uri ($uri)
    {
        static::$_api_endpoint = $uri;
        if ( static::$_http_client !== NULL ) {
            static::logout();
        }
    }


    /**
     * Change the version string embedded in the Scale Dynamix API URI.
     * This will force a logout() on an active connection.
     *
     * @param   string      $version
     *
     * @return  void
     */
    public static function set_api_version ($version)
    {
        static::$_api_version = $version;
        if ( static::$_http_client !== NULL ) {
            static::logout();
        }
    }


    /**
     * Return a list of the cloud platform providers enabled for this account.
     *
     * @throws  RuntimeException
     *
     * @return  array
     */
    public static function get_providers ()
    {
        if ( static::$_http_client === NULL ) {
            throw new RuntimeException('API not available; you must ::login() first', EACCESS);
        }
        $response = static::_exec('providers');
        return ($response->body)['result']['providers'];
    }


    /**
     * Return a list of the "stacks" (cloud platform instances) available for
     * this account.
     *
     * @throws  RuntimeException
     *
     * @return  array
     */
    public static function get_stacks ()
    {
        if ( ! empty(static::$_cache['stacks']) ) {
            return static::$_cache['stacks'];
        }
        if ( static::$_http_client === NULL ) {
            throw new RuntimeException('API not available; you must ::login() first', EACCESS);
        }
        $response = static::_exec('stacks');
        if ( ! isset($response->body['result']['stacks']) || ! is_array($response->body['result']['stacks']) ) {
            throw new RuntimeException('Invalid API response when retrieving stacks', EUNDEF);
        }
        foreach ($response->body['result']['stacks'] as $stackinfo) {
            //  Create a new stack.
            //  TODO
            throw new RuntimeException('This API client does not yet support stack creation', ENOSYS);
        }
        return static::$_cache['stacks'];
    }


    /**
     * Return a list of the sites in this account.
     *
     * @throws  RuntimeException
     *
     * @return  array
     */
    public static function get_sites ()
    {
        if ( ! empty(static::$_cache['sites']) ) {
            return static::$_cache['sites'];
        }
        if ( static::$_http_client === NULL ) {
            throw new RuntimeException('API not available; you must ::login() first', EACCESS);
        }
        $response = static::_exec('sites');
        if ( ! isset($response->body['result']['sites']) || ! is_array($response->body['result']['sites']) ) {
            throw new RuntimeException("Invalid API response when retrieving sites", EUNDEF);
        }
        foreach ($response->body['result']['sites'] as $siteinfo) {
            static::$_cache['sites'][] = new Site($siteinfo);
        }
        return static::$_cache['sites'];
    }


    /**
     * Return all available metadata for a given site.
     *
     * @param   mixed       $site_id
     *
     * @throws  RuntimeException
     *
     * @return  array
     */
    public static function get_site_metadata ($site_id)
    {
        if ( static::$_http_client === NULL ) {
            throw new RuntimeException('API not available; you must ::login() first', EACCESS);
        }
        if ( ! static::_is_valid_id($site_id) ) {
            throw new RuntimeException("Can't retrieve metadata for this site ID: $site_id", EINVAL);
        }
        $response = static::_exec("sites/$site_id");
        if ( ! isset($response->body['result']) || ! is_array($response->body['result']) ) {
            throw new RuntimeException("Invalid API response when retrieving metadata for site $site_id", EUNDEF);
        }
        return $response->body['result'];
    }


    /**
     * Create a new site. This function will not clone an existing site; use
     * clone_site() for that instead. According to ScaleDynamix support on
     * 2022-01-25, site names must be A-Za-z0-9-; I assume this is meant to
     * be the standard subdomain-part pattern, so $name is also not allowed
     * to end in a "-".
     *
     * @param   string      $name
     * @param   mixed       $stack_id
     * @param   int         $type
     *
     * @throws  RuntimeException
     *
     * @return  Site
     */
    public static function create_new_site ($name, $stack_id, $type)
    {
        if ( static::$_http_client === NULL ) {
            throw new RuntimeException('API not available; you must ::login() first', EACCESS);
        }
        if ( $type === CMS_CLONE ) {
            throw new RuntimeException('Use clone_site() to copy a site, not create_new_site()', EINVAL);
        }
        if ( preg_match('/^[A-Za-z0-9]+(-[A-Za-z0-9]+)*$/', $name) !== 1 ) {
            throw new RuntimeException('Site names can only contain A-Z, 0-9, and "-", and cannot end with a "-"', EINVAL);
        }
        $response = static::_exec('sites', 'POST', ['name' => $name, 'stack_id' => $stack_id, 'type' => $type, 'source_id' => 0]);
        if ( ! isset($response->body['result']) || ! is_array($response->body['result']) ) {
            throw new RuntimeException("Invalid API response when creating \"$name\"", EUNDEF);
        }
        //  Flush the sites cache.
        static::$_cache['sites'] = [];
        //  ScaleDynamix API returns a JSON object that gets interpreted by
        //  PHP as a nested array.
        $new_site_values = $response->body['result'][0];
        return new Site($new_site_values);
    }


    /**
     * Clone an existing site. See notes in create_new_site() regarding site names.
     *
     * @param   string      $name
     * @param   mixed       $stack_id
     * @param   mixed       $clone_id
     *
     * @throws  RuntimeException
     *
     * @return  Site
     */
    public static function clone_site ($name, $stack_id, $clone_id)
    {
        if ( static::$_http_client === NULL ) {
            throw new RuntimeException('API not available; you must ::login() first', EACCESS);
        }
        if ( ! static::_is_valid_id($clone_id) ) {
            throw new RuntimeException("Can't clone this site ID: $clone_id", EINVAL);
        }
        if ( preg_match('/^[A-Za-z0-9]+(-[A-Za-z0-9]+)*$/', $name) !== 1 ) {
            throw new RuntimeException('Site names can only contain A-Z, 0-9, and "-", and cannot end with a "-"', EINVAL);
        }
        $response = static::_exec('sites', 'POST', ['name' => $name, 'stack_id' => $stack_id, 'type' => CMS_CLONE, 'clonesourceid' => $clone_id]);
        if ( ! isset($response->body['result']) || ! is_array($response->body['result']) ) {
            throw new RuntimeException("Invalid API response when cloning site $clone_id", EUNDEF);
        }
        //  Flush the sites cache.
        static::$_cache['sites'] = [];
        //  ScaleDynamix API returns a JSON object that gets interpreted by
        //  PHP as a nested array.
        $new_site_values = $response->body['result'][0];
        return new Site($new_site_values);
    }


    /**
     * Return the list of tags that have been added to a site.
     * Tag IDs are returned along with the tags because a tag ID is required
     * when deleting a tag.
     *
     * @param   mixed       $site_id
     *
     * @throws  RuntimeException
     *
     * @return  array
     */
    public static function get_tags ($site_id)
    {
        if ( static::$_http_client === NULL ) {
            throw new RuntimeException('API not available; you must ::login() first', EACCESS);
        }
        if ( ! static::_is_valid_id($site_id) ) {
            throw new RuntimeException("Can't delete this site ID: $site_id", EINVAL);
        }
        $response = static::_exec("tags/$site_id");
        if ( ! isset($response->body['result']['tags']) || ! is_array($response->body['result']['tags']) ) {
            throw new RuntimeException("Invalid API response when retrieving tags for site $site_id", EUNDEF);
        }
        return $response->body['result']['tags'];
    }


    /**
     * Add a new tag to a site. The Scale Dynamix API appears to only support
     * one tag per API call. This function returns the new list of tags
     * associated with the site so that the Site object can update its internal
     * tag cache with the new tag's ID.
     *
     * @param   mixed       $site_id
     * @param   string      $tag
     *
     * @throws  RuntimeException
     *
     * @return  array
     */
    public static function add_tag ($site_id, $tag)
    {
        if ( static::$_http_client === NULL ) {
            throw new RuntimeException('API not available; you must ::login() first', EACCESS);
        }
        if ( ! static::_is_valid_id($site_id) ) {
            throw new RuntimeException("Can't add a tag to this site ID: $site_id", EINVAL);
        }
        $response = static::_exec("tags/$site_id", 'POST', ['tag' => $tag]);
        if ( ! isset($response->body['result']['tags']) || ! is_array($response->body['result']['tags']) ) {
            throw new RuntimeException("Invalid API response when retrieving tags for site $site_id", EUNDEF);
        }
        return $response->body['result']['tags'];
    }


    /**
     * Remove a tag from a site. The Site object must pass the ID of the tag
     * to be deleted. The Scale Dynamix API appears to only support one tag ID
     * per API call. This function returns true if the tag was successfully deleted.
     *
     * @param   mixed       $site_id
     * @param   string      $tag_id
     *
     * @throws  RuntimeException
     *
     * @return  boolean
     */
    public static function delete_tag ($site_id, $tag_id)
    {
        if ( static::$_http_client === NULL ) {
            throw new RuntimeException('API not available; you must ::login() first', EACCESS);
        }
        if ( ! static::_is_valid_id($site_id) ) {
            throw new RuntimeException("Can't add a tag to this site ID: $site_id", EINVAL);
        }
        $response = static::_exec("tags/$site_id", 'DELETE', ['tag_id' => $tag_id]);
        if ( ! isset($response->body['result']['tags']) || ! is_array($response->body['result']['tags']) ) {
            throw new RuntimeException("Invalid API response when retrieving tags for site $site_id", EUNDEF);
        }
        return $response['success'];
    }


    /**
     * Add a new domain name to a site. Returns the internal ID of the new domain.
     * The API can only add one domain per call.
     *
     * @param   mixed       $site_id
     * @param   string      $domain
     *
     * @throws  RuntimeException
     *
     * @return  int
     */
    public static function add_domain ($site_id, $domain)
    {
        if ( static::$_http_client === NULL ) {
            throw new RuntimeException('API not available; you must ::login() first', EACCESS);
        }
        if ( ! static::_is_valid_id($site_id) ) {
            throw new RuntimeException("Can't add a domain to this site ID: $site_id", EINVAL);
        }
        if ( preg_match('/^[a-zA-Z0-9_.-]+$/', $domain) !== 1 ) {
            throw new RuntimeException("Can't add this domain: $domain", EINVAL);
        }
        $response = static::_exec("domains/$site_id", 'POST', ['domain' => $domain]);
        if ( ! isset($response->body['result']['id']) ) {
            throw new RuntimeException("Invalid API response when adding the domain \"$domain\" for site $site_id", EUNDEF);
        }
        return $response->body['result']['id'];
    }


    /**
     * Retrieve the list of domains attached to a site.
     * All of a site's domains are included in the response for a site metadata
     * request, so it would probably be more efficient to request and cache the
     * site's metadata and just return the domains from there. But, in the
     * interests of having complete support for their API, this is here anyway.
     * The Site object will request metadata instead of domains or tags.
     *
     * @param   mixed       $site_id
     *
     * @throws  RuntimeException
     *
     * @return  array
     */
    public static function get_domains ($site_id)
    {
        if ( static::$_http_client === NULL ) {
            throw new RuntimeException('API not available; you must ::login() first', EACCESS);
        }
        if ( ! static::_is_valid_id($site_id) ) {
            throw new RuntimeException("Can't get a list of domains for this site ID: $site_id", EINVAL);
        }
        $response = static::_exec("domains/$site_id");
        if ( ! isset($response->body['result']['domains']) || ! is_array($response->body['result']['domains']) ) {
            throw new RuntimeException("Invalid API response when getting domains for site $site_id", EUNDEF);
        }
        return $response->body['result']['domains'];
    }


    /**
     * Change the primary domain for a site.
     *
     * @param   mixed       $site_id
     * @param   mixed       $domain_id
     *
     * @throws  RuntimeException
     *
     * @return  boolean
     */
    public static function set_primary_domain ($site_id, $domain_id)
    {
        if ( static::$_http_client === NULL ) {
            throw new RuntimeException('API not available; you must ::login() first', EACCESS);
        }
        if ( ! static::_is_valid_id($site_id) ) {
            throw new RuntimeException("Can't set the primary domain for this site ID: $site_id", EINVAL);
        }
        if ( ! static::_is_valid_id($domain_id) ) {
            throw new RuntimeException("This does not look like a valid domain ID: $domain_id", EINVAL);
        }
        $response = static::_exec("domains/$site_id", 'PUT', ['domain_id' => $domain_id]);
        if ( ! isset($response->body['result']['id']) ) {
            throw new RuntimeException("Invalid API response when setting the primary domain for site $site_id", EUNDEF);
        }
        return $response->body['result']['success'];
    }


    /**
     * Delete a domain from a site. The API will return an error if you try to
     * delete the primary domain, so call set_primary_domain() first as needed.
     *
     * @param   mixed       $site_id
     * @param   mixed       $domain_id
     *
     * @throws  RuntimeException
     *
     * @return  boolean
     */
    public static function delete_domain ($site_id, $domain_id)
    {
        if ( static::$_http_client === NULL ) {
            throw new RuntimeException('API not available; you must ::login() first', EACCESS);
        }
        if ( ! static::_is_valid_id($site_id) ) {
            throw new RuntimeException("This does not look like a valid site ID: $site_id", EINVAL);
        }
        if ( ! static::_is_valid_id($domain_id) ) {
            throw new RuntimeException("This does not look like a valid domain ID: $domain_id", EINVAL);
        }
        $response = static::_exec("domains/$site_id", 'DELETE', ['domain_id' => $domain_id]);
        if ( ! isset($response->body['result']['id']) ) {
            throw new RuntimeException("Invalid API response when deleting a domain from site $site_id", EUNDEF);
        }
        return $response->body['result']['success'];
    }


    /**
     * Delete a site.
     *
     * @param   mixed       $site_id
     *
     * @throws  RuntimeException
     *
     * @return  boolean
     */
    public static function delete_site ($site_id)
    {
        if ( static::$_http_client === NULL ) {
            throw new RuntimeException('API not available; you must ::login() first', EACCESS);
        }
        if ( ! static::_is_valid_id($site_id) ) {
            throw new RuntimeException("Can't delete this site ID: $site_id", EINVAL);
        }
        $response = static::_exec("sites/$site_id", 'DELETE');
        if ( ! isset($response->body['result']) || ! is_array($response->body['result']) ) {
            throw new RuntimeException("Invalid API response when deleting site $site_id", EUNDEF);
        }
        //  Flush the sites cache.
        static::$_cache['sites'] = [];
        return $response['success'];
    }


    /**
     * "Log out" from the API: this is implemented by destroying the internal
     * http client object and cleaning up caches. Further calls to the API
     * will fail unless login() is called again with the appropriate credentials.
     *
     * @throws  RuntimeException
     *
     * @return  void
     */
    public static function logout ()
    {
        static::$_cache = ['stacks' => [], 'sites' => []];
        static::$_api_key = '';
        static::$_http_client = NULL;
    }
}
