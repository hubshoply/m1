<?php

/**
 * API test diagnostic program.
 *
 * - Simulates token request authorization & provides exchange
 * 
 * PHP Version 5
 * 
 * @category  Class
 * @package   Groove_Hubshoply
 * @author    Groove Commerce
 * @copyright 2017 Groove Commerce, LLC. All Rights Reserved.
 *
 * LICENSE
 * 
 * The MIT License (MIT)
 * Copyright (c) 2017 Groove Commerce, LLC.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

/**
 * Class declaration
 *
 * @category Class_Type_Model
 * @package  Groove_Hubshoply
 * @author   Groove Commerce
 */

class Groove_Hubshoply_Model_Diagnostic_Api
    implements Groove_Hubshoply_Model_Diagnostic_Interface
{

    const VERIFY_PEER = false;

    /**
     * Determine whether the authorization endpoint is accessible.
     * 
     * @return boolean
     */
    private function _checkOauthAuthorizationEndpoint($verifyPeer = true)
    {
        $http = new Varien_Http_Adapter_Curl();
        $url  = Mage::getSingleton('groove_hubshoply/config')->getAdminUrl('adminhtml/oauth_authorize');

        $http->addOption(CURLOPT_NOBODY, true);

        if (!$verifyPeer) {
            $http->addOption(CURLOPT_SSL_VERIFYPEER, false);
        }

        $http->write('GET', $url, null, '');

        $response = $http->read();

        $http->close();

        return Zend_Http_Response::extractCode($response) === 200;
    }

    /**
     * Generate a one-time use token for this test.
     *
     * - Uses an existing authorized token if available.
     * - Autogenerates a new pre-authorized token if needed.
     *
     * @param Mage_Oauth_Model_Consumer $consumer The consumer model.
     * 
     * @return Mage_Oauth_Model_Token
     */
    private function _generateToken(Mage_Oauth_Model_Consumer $consumer)
    {
        $helper     = Mage::helper('oauth');
        $collection = Mage::getResourceModel('oauth/token_collection')
            ->addFieldToFilter('authorized', 1)
            ->addFilterByConsumerId($consumer->getId())
            ->addFilterByType(Mage_Oauth_Model_Token::USER_TYPE_ADMIN)
            ->addFilterByRevoked(false);

        if (!$collection->getSize()) {
            $token = Mage::getModel('oauth/token')
                ->setCallbackUrl($consumer->getCallbackUrl())
                ->setConsumerId($consumer->getId())
                ->setAdminId(1)
                ->setType(Mage_Oauth_Model_Token::TYPE_REQUEST)
                ->setAuthorized(1)
                ->setExpires(date('Y-m-d h:i:s', time() + 300))
                ->setIsTemporary(true)
                ->convertToAccess();
        } else {
            $token = $collection->getFirstItem();
        }

        return $token;
    }

    /**
     * Generate a pre-configured OAuth/HTTP client.
     * 
     * @param Mage_Oauth_Model_Consumer $consumer  The consumer model.
     * @param Mage_Oauth_Model_Token    $mageToken The authorized Magento token.
     * 
     * @return Zend_Oauth_Client
     */
    private function _getClient(Mage_Oauth_Model_Consumer $consumer, Mage_Oauth_Model_Token $mageToken)
    {
        $config = array(
            'siteUrl'           => Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB),
            'callbackUrl'       => $consumer->getCallbackUrl(),
            'consumerKey'       => $consumer->getKey(),
            'consumerSecret'    => $consumer->getSecret(),
        );

        $token  = new Zend_Oauth_Token_Access();
        $client = new Zend_Oauth_Client($config);

        $token->setToken($mageToken->getToken());
        $token->setTokenSecret($mageToken->getSecret());

        $client->setToken($token);

        return $client;
    }

    /**
     * Test the products REST API.
     * 
     * @param Mage_Oauth_Model_Consumer $consumer The consumer model.
     * @param Mage_Oauth_Model_Token    $token    The authorized Magento token.
     * 
     * @return boolean
     */
    private function _testProductsApi(Mage_Oauth_Model_Consumer $consumer, Mage_Oauth_Model_Token $token)
    {
        $client = $this->_getClient($consumer, $token);

        $client->setHeaders(
                array(
                    'Content-Type'  => 'application/json',
                    'Accept'        => '*/*',
                )
            )
            ->setMethod('GET')
            ->setUri(Mage::getUrl('api/rest/products'));

        $response = $client->request();

        return $response->extractCode($response->asString()) === 200;
    }

    /**
     * Return dependencies.
     * 
     * @return array
     */
    public function getDependencies()
    {
        return array(
            'enabled'   => self::STATUS_PASS,
            'consumer'  => self::STATUS_PASS,
        );
    }

    /**
     * Perform a sample REST sequence to validate connectivity.
     *
     * @param Varien_Object $object The item to diagnose.
     * 
     * @return void
     */
    public function run(Varien_Object $object)
    {
        $object->setStatus(self::STATUS_PASS);

        if (!$this->_checkOauthAuthorizationEndpoint(false)) {
            $object->setStatus(self::STATUS_FAIL)
                ->setDetails('Authorization endpoint could not be reached.');

            return;
        } else if ( self::VERIFY_PEER && !$this->_checkOauthAuthorizationEndpoint() ) {
            $object->setStatus(self::STATUS_FAIL)
                ->setDetails('SSL peer verification failed on authorization endpoint.');

            return;
        }

        try {
            $consumer = Mage::helper('groove_hubshoply/oauth')->getConsumer(null, false);
        } catch (Mage_Core_Exception $error) {
            $object->setStatus(self::STATUS_FAIL)
                ->setDetails(sprintf('Consumer validation error: %s', $error->getMessage()));

            return;
        } catch (Exception $error) {
            $object->setStatus(self::STATUS_FAIL)
                ->setDetails(sprintf('Internal server error on consumer validation: %s', $error->getMessage()));

            return;
        }

        try {
            $token = $this->_generateToken($consumer);
        } catch (Mage_Core_Exception $error) {
            $object->setStatus(self::STATUS_FAIL)
                ->setDetails(sprintf('Token validation error: %s', $error->getMessage()));

            return;
        } catch (Exception $error) {
            $object->setStatus(self::STATUS_FAIL)
                ->setDetails(sprintf('Internal server error on token validation: %s', $error->getMessage()));

            return;
        }

        if (!$this->_testProductsApi($consumer, $token)) {
            $object->setStatus(self::STATUS_FAIL)
                ->setDetails('Products API test did not succeed.');
        }

        if ($token->getIsTemporary()) {
            $token->delete();
        }
    }

}