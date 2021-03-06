<?php

/**
 * HubShop.ly Magento
 * 
 * Debug helper.
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
 * @category Class_Type_Helper
 * @package  Groove_Hubshoply
 * @author   Groove Commerce
 */
 
class Groove_Hubshoply_Helper_Debug
    extends Mage_Core_Helper_Abstract
{

    private $_canRecord = true;

    /**
     * Create a new log entry.
     * 
     * @param string  $message The data to log.
     * @param integer $level   Optional log level.
     * 
     * @return Groove_Hubshoply_Helper_Debug
     */
    public function log($message, $level = Zend_Log::DEBUG)
    {
        try {
            if ($this->_canRecord) {
                Mage::getModel('groove_hubshoply/log')
                    ->setMessage($message)
                    ->setLevel($level)
                    ->save();
            } else {
                Mage::log($message, $level);
            }
        } catch (Exception $error) {
            Mage::logException($error);
        }

        return $this;
    }

    /**
     * Log an exception.
     * 
     * @param Exception $error The exception thrown.
     * 
     * @return Groove_Hubshoply_Helper_Debug
     */
    public function logException(Exception $error)
    {
        $this->log($error->getMessage(), Zend_Log::ERR);

        return $this;
    }

    /**
     * Set the log recording flag.
     * 
     * @param boolean $flag
     *
     * @return Groove_Hubshoply_Helper_Debug
     */
    public function setCanRecord($flag = true)
    {
        $this->_canRecord = $flag;

        return $this;
    }

}