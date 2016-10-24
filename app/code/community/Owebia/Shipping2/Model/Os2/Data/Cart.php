<?php

/**
 * Copyright (c) 2008-14 Owebia
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"),
 * to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense,
 * and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 *
 * @website    http://www.owebia.com/
 * @project    Magento Owebia Shipping 2 module
 * @author     Antoine Lemoine
 * @license    http://www.opensource.org/licenses/MIT  The MIT License (MIT)
**/

class Owebia_Shipping2_Model_Os2_Data_Cart extends Owebia_Shipping2_Model_Os2_Data_Abstract
{
    protected $additionalAttributes = array('coupon_code', 'weight_unit', 'weight_for_charge', 'free_shipping');
    protected $freeShipping;
    protected $_items;
    protected $_quote;
    protected $_options;

    public function __construct($arguments)
    {
        parent::__construct();
        $request = $arguments['request'];
        $this->_options = $arguments['options'];

        $this->_data = array(
            // Do not use quote to retrieve values, totals are not available
            'price-tax+discount' => null,//(double)$request->getData('package_value_with_discount'), // Bad value in backoffice orders
            'price-tax-discount' => null,//(double)$request->getData('package_value'),
            'price+tax+discount' => null,
            'price+tax-discount' => null,
            'weight' => $request->getData('package_weight'),
            'qty' => $request->getData('package_qty'),
            'free_shipping' => $request->getData('free_shipping'),
        );

        $cartItems = array();
        $items = $request->getAllItems();
        $quoteTotalCollected = false;
        $bundleProcessChildren = isset($this->_options['bundle']['process_children']) && $this->_options['bundle']['process_children'];
        foreach ($items as $item) {
            $product = $item->getProduct();
            if ($product instanceof Mage_Catalog_Model_Product) {
                $key = null;
                if ($item instanceof Mage_Sales_Model_Quote_Address_Item) { // Multishipping
                    $key = $item->getQuoteItemId();
                } else if ($item instanceof Mage_Sales_Model_Quote_Item) { // Onepage checkout
                    $key = $item->getId();
                }
                $cartItems[$key] = $item;
            }
        }

        // Do not use quote to retrieve values, totals are not available
        $totalInclTaxWithoutDiscount = 0;
        $totalExclTaxWithoutDiscount = 0;
        $totalInclTaxWithDiscount = 0;
        $totalExclTaxWithDiscount = 0;
        $this->_items = array();
        foreach ($cartItems as $item) {
            $type = $item->getProduct()->getTypeId();
            //echo $item->getProduct()->getTypeId().', '.$item->getQty().'<br/>';
            $parentItemId = $item->getData('parent_item_id');
            $parentItem = isset($cartItems[$parentItemId]) ? $cartItems[$parentItemId] : null;
            $parentType = isset($parentItem) ? $parentItem->getProduct()->getTypeId() : null;
            if ($type!='configurable') {
                if ($type=='bundle' && $bundleProcessChildren) {
                    $this->_data['qty'] -= $item->getQty();
                    continue;
                }
                if ($parentType=='bundle') {
                    if (!$bundleProcessChildren) continue;
                    else $this->_data['qty'] += $item->getQty();
                }
                $this->_items[] = Mage::getModel('owebia_shipping2/Os2_Data_CartItem', array('item' => $item, 'parent_item' => $parentItem, 'options' => $this->_options));
            }
            //foreach ($item->getData() as $index => $value) echo "$index = $value<br/>\n";
            $totalExclTaxWithoutDiscount += $item->getData('base_row_total');
            $totalExclTaxWithDiscount += $item->getData('base_row_total') - $item->getData('base_discount_amount');
            $totalInclTaxWithDiscount += $item->getData('base_row_total') - $item->getData('base_discount_amount') + $item->getData('tax_amount');
            $totalInclTaxWithoutDiscount += $item->getData('base_row_total_incl_tax');
        }
        $this->_data['price-tax+discount'] = $totalExclTaxWithDiscount;
        $this->_data['price-tax-discount'] = $totalExclTaxWithoutDiscount;
        $this->_data['price+tax+discount'] = $totalInclTaxWithDiscount;
        $this->_data['price+tax-discount'] = $totalInclTaxWithoutDiscount;

        //echo '<pre>Owebia_Shipping2_Model_Os2_Data_Abstract::__construct<br/>';foreach ($this->_data as $n => $v){echo "\t$n => ".(is_object($v) ? get_class($v) : (is_array($v) ? 'array' : $v))."<br/>";}echo '</pre>';
    }

    protected function _getQuote()
    {
        return Mage::getModel('owebia_shipping2/Os2_Data_Quote');
    }

    protected function _load($name)
    {
        switch ($name) {
            case 'weight_for_charge':
                $weightForCharge = $this->getData('weight');
                foreach ($this->_items as $item) {
                    if ($item->getData('free_shipping')) $weightForCharge -= $item->getData('weight');
                }
                return $weightForCharge;
            case 'coupon_code':
                $couponCode = null;
                $quote = $this->_getQuote();
                return $quote->getData('coupon_code');
            case 'weight_unit':
                return Mage::getStoreConfig('owebia_shipping2/general/weight_unit');
        }
        return parent::_load($name);
    }

    public function __set($name, $value)
    {
        switch ($name) {
            case 'items':
                return $this->_items = $value;
        }
        return parent::__set($name, $value);
    }

    public function getData($name)
    {
        switch ($name) {
            case 'items':
                return $this->_items;
            case 'free_shipping':
                if (isset($this->freeShipping)) return $this->freeShipping;
                $freeShipping = parent::getData('free_shipping');
                if (!$freeShipping) {
                    foreach ($this->_items as $item) {
                        $freeShipping = $item->getData('free_shipping');
                        if (!$freeShipping) break;
                    }
                }
                return $this->freeShipping = $freeShipping;
        }
        return parent::getData($name);
    }
}
