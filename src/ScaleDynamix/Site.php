<?php

/*******************************************************************************
*                                                                              *
*   Asinius\ScaleDynamix\Site                                                  *
*                                                                              *
*   Convenience class that encapsulates site-related properties and functions  *
*   in the Scale Dynamix API.                                                  *
*                                                                              *
*   This class includes some additional logic that tries to minimize the       *
*   number of calls made to the API. For example, tags and domains are cached  *
*   by a single request for metadata, and input parameters are aggressively    *
*   verified before any API calls are made.                                    *
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

use RuntimeException;

/*******************************************************************************
*                                                                              *
*   \Asinius\ScaleDynamix\Site                                                 *
*                                                                              *
*******************************************************************************/

class Site
{

    protected $_values  = [];
    protected $_deleted = false;


    /**
     * Convert the API's representation of tags (id => tagname) into a more
     * useful internal structure.
     *
     * @param   array       $tags
     *
     * @internal
     *
     * @return  void
     */
    protected function _import_tags ($tags)
    {
        $this->_values['tags'] = array_merge($this->_values['tags'], array_flip($tags));
    }


    /**
     * Convert the API's domains array into a more convenient data structure.
     *
     * @param   array       $domains
     *
     * @internal
     *
     * @throws  RuntimeException
     *
     * @return  void
     */
    protected function _import_domains ($domains)
    {
        $site_id = $this->_values['id'];
        foreach ($domains as $domain) {
            if ( ! array_key_exists('domain', $domain) ) {
                throw new RuntimeException("Missing hostname in domain structure for site ID $site_id", EUNDEF);
            }
            $hostname = $domain['domain'];
            if ( ! array_key_exists('id', $domain) ) {
                throw new RuntimeException("Missing id for hostname \"$hostname\" in site ID $site_id", EUNDEF);
            }
            unset($domain['domain']);
            $this->_values['domains'][$hostname] = $domain;
        }
    }


    /**
     * Create and return a new Scale Dynamix Site object.
     *
     * @param   array       $values
     */
    public function __construct ($values)
    {
        \Asinius\Asinius::assert_parent(['Asinius\ScaleDynamix\ApiClient']);
        $this->_values = $values;
        $this->_values['metadata'] = [];
        $this->_values['tags'] = [];
        $this->_values['domains'] = [];
    }


    /**
     * Return one of the read-only properties for this Site.
     *
     * @param   string      $property
     *
     * @throws  RuntimeException
     *
     * @return  mixed
     */
    public function __get ($property)
    {
        $site_id = $this->_values['id'];
        if ( $this->_deleted ) {
            throw new RuntimeException("Site ID $site_id can not be accessed because it has been deleted from your account", EUNDEF);
        } 
        if ( array_key_exists($property, $this->_values) ) {
            return $this->_values[$property];
        }
        switch ($property) {
            case 'metadata':
                $metadata = ApiClient::get_site_metadata($this->_values['id']);
                if ( ! is_array($metadata) || ! isset($metadata[0]) || count($metadata) !== 1 ) {
                    throw new RuntimeException("The API returned an unexpected response when retrieving metadata for site ID $site_id", EUNDEF);
                }
                $metadata = reset($metadata);
                $tags = $metadata['tags'];
                $domains = $metadata['domains'];
                unset($metadata['tags']);
                unset($metadata['domains']);
                $this->_import_tags($tags);
                $this->_import_domains($domains);
                $this->_values['metadata'] = $metadata;
                return $this->_values['metadata'];
            case 'domains':
            case 'tags':
                if ( empty($this->_values[$property]) && empty($this->_values['metadata']) ) {
                    $this->__get('metadata');
                }
                return $this->_values[$property];
            default:
                throw new RuntimeException("Property does not exist: $property", EINVAL);
        }
    }


    /**
     * Trap any attempts to set a property value and throw() an error.
     *
     * @param   string      $property
     * @param   mixed       $value
     *
     * @throws  RuntimeException
     *
     * @return  void
     */
    public function __set ($property, $value)
    {
        throw new RuntimeException(__CLASS__ . ' is read-only', EACCESS);
    }


    /**
     * Clone this Site into a new Site.
     *
     * @param   string      $name
     * @param   string|int  $to_stack
     *
     * @throws  RuntimeException
     *
     * @return  Site
     */
    public function clone ($name, $to_stack)
    {
        if ( $this->_deleted ) {
            throw new RuntimeException('Site ID ' . $this->_values['id'] . ' can not be accessed because it has been deleted from your account', EUNDEF);
        }
        return ApiClient::clone_site($name, $to_stack, $this->_values['id']);
    }


    /**
     * Add a new tag to this Site.
     *
     * @param   string      $tag
     *
     * @throws  RuntimeException
     *
     * @return  void
     */
    public function add_tag ($tag)
    {
        if ( $this->_deleted ) {
            throw new RuntimeException('Site ID ' . $this->_values['id'] . ' can not be accessed because it has been deleted from your account', EUNDEF);
        }
        $this->_import_tags(ApiClient::add_tag($this->_values['id'], $tag));
    }


    /**
     * Remove a tag from this site.
     *
     * @param   string      $tag
     *
     * @throws  RuntimeException
     *
     * @return  void
     */
    public function delete_tag ($tag)
    {
        if ( $this->_deleted ) {
            throw new RuntimeException('Site ID ' . $this->_values['id'] . ' can not be accessed because it has been deleted from your account', EUNDEF);
        }
        //  Ensure that the list of tags for this site is already cached.
        $tags = $this->__get('tags');
        if ( ! array_key_exists($tag, $tags) ) {
            return;
        }
        $tag_id = $tags[$tag];
        ApiClient::delete_tag($this->_values['id'], $tag_id);
        unset($this->_values['tags'][$tag]);
    }


    /**
     * Add a new "domain" (hostname) to this Site.
     *
     * @param   string      $hostname
     *
     * @throws  RuntimeException
     *
     * @return  void
     */
    public function add_domain ($hostname)
    {
        if ( $this->_deleted ) {
            throw new RuntimeException('Site ID ' . $this->_values['id'] . ' can not be accessed because it has been deleted from your account', EUNDEF);
        }
        $domain_id = ApiClient::add_domain($this->_values['id'], $hostname);
        $this->_import_domains([['domain' => $hostname, 'id' => $domain_id]]);
    }


    /**
     * Change the primary "domain" (hostname) for this Site.
     *
     * @param   string      $hostname
     *
     * @throws  RuntimeException
     *
     * @return  void
     */
    public function set_primary_domain ($hostname)
    {
        if ( $this->_deleted ) {
            throw new RuntimeException('Site ID ' . $this->_values['id'] . ' can not be accessed because it has been deleted from your account', EUNDEF);
        }
        //  Ensure that the list of domains for this site is already cached.
        $domains = $this->__get('domains');
        if ( ! array_key_exists($hostname, $domains) ) {
            throw new RuntimeException("Can't make \"$hostname\" the primary domain for site ID " . $this->_values['id'] . " because this domain hasn't been added to this site", EINVAL);
        }
        if ( isset($this->_values['domains'][$hostname]['primary']) && $this->_values['domains'][$hostname]['primary'] == true ) {
            return;
        }
        $success = ApiClient::set_primary_domain($this->_values['id'], $domains[$hostname]['id']);
        if ( ! $success ) {
            throw new RuntimeException("Scale Dynamix failed to set \"$hostname\" as the primary domain for site ID " . $this->_values['id'], EUNDEF);
        }
        //  Unset the previous primary domain.
        foreach ($this->_values['domains'] as $hostname => $values) {
            if ( isset($values['primary']) && $values['primary'] == true ) {
                $this->_values['domains'][$hostname]['primary'] = false;
                break;
            }
        }
        $this->_values['domains'][$hostname]['primary'] = true;
    }


    /**
     * Remove a "domain" (hostname) from this Site.
     *
     * @param   string      $hostname
     *
     * @throws  RuntimeException
     *
     * @return  void
     */
    public function delete_domain ($hostname)
    {
        if ( $this->_deleted ) {
            throw new RuntimeException('Site ID ' . $this->_values['id'] . ' can not be accessed because it has been deleted from your account', EUNDEF);
        }
        //  Ensure that the list of domains for this site is already cached.
        $domains = $this->__get('domains');
        if ( ! array_key_exists($hostname, $domains) ) {
            return;
        }
        if ( isset($this->_values['domains'][$hostname]['primary']) && $this->_values['domains'][$hostname]['primary'] == true ) {
            //  Find another hostname and make that one primary before deleting
            //  this one.
            foreach ($this->_values['domains'] as $hostname => $values) {
                if ( isset($values['primary']) && $values['primary'] == false ) {
                    $this->_set_primary_domain($hostname);
                    break;
                }
            }
        }
        if ( ApiClient::delete_domain($this->_values['id'], $this->_values['domains'][$hostname]['id']) ) {
            unset($this->_values['domains'][$hostname]);
        }
    }


    /**
     * Delete this Site from the account.
     *
     * @throws  RuntimeException
     *
     * @return  void
     */
    public function delete ()
    {
        if ( $this->_deleted ) {
            throw new RuntimeException('Site ID ' . $this->_values['id'] . ' can not be accessed because it has been deleted from your account', EUNDEF);
        }
        $this->_deleted = ApiClient::delete_site($this->_values['id']);
    }
}
