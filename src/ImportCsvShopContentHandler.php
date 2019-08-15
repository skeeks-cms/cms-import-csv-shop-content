<?php
/**
 * @link https://cms.skeeks.com/
 * @copyright Copyright (c) 2010 SkeekS
 * @license https://cms.skeeks.com/license/
 * @author Semenov Alexander <semenov@skeeks.com>
 */

namespace skeeks\cms\importCsvShopContent;

use skeeks\cms\importCsv\helpers\CsvImportRowResult;
use skeeks\cms\importCsvContent\ImportCsvContentHandler;
use skeeks\cms\models\CmsContent;
use skeeks\cms\shop\models\ShopCmsContentElement;
use skeeks\cms\shop\models\ShopProduct;
use skeeks\cms\shop\models\ShopProductPrice;
use yii\base\Exception;
use yii\db\ActiveQuery;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;
use yii\widgets\ActiveForm;

/**
 * @author Semenov Alexander <semenov@skeeks.com>
 */
class ImportCsvShopContentHandler extends ImportCsvContentHandler
{
    public function init()
    {
        parent::init();

        $this->name = \Yii::t('skeeks/importCsvShopContent', '[CSV] Import of products');
    }

    /**
     * @return array
     */
    public function getAvailableFields()
    {
        $fields = parent::getAvailableFields();

        foreach ((new ShopProduct())->attributeLabels() as $key => $name) {
            $fields['shop.'.$key] = $name.' [магазин]';
        }

        foreach (\Yii::$app->shop->shopTypePrices as $price) {
            $fields['priceValue.'.$price->id] = $price->name.' [Значение цены]';
            $fields['priceCurrency.'.$price->id] = $price->name.' [Валюта цены]';
        }

        return $fields;
    }

    public function rules()
    {
        return ArrayHelper::merge(parent::rules(), [
        ]);
    }


    public function attributeLabels()
    {
        return ArrayHelper::merge(parent::attributeLabels(), [
        ]);
    }


    /**
     * @param ActiveForm $form
     */
    public function renderConfigForm(ActiveForm $form)
    {
        $this->renderCsvConfigForm($form);

        echo $form->field($this, 'content_id')->listBox(
            array_merge(['' => ' - '], CmsContent::getDataForSelect(true, function (ActiveQuery $activeQuery) {
                $activeQuery->andWhere([
                    'id' => \yii\helpers\ArrayHelper::map(\skeeks\cms\shop\models\ShopContent::find()->all(), 'content_id', 'content_id'),
                ]);
            })), [
            'size'             => 1,
            'data-form-reload' => 'true',
        ]);
    }


    protected function _initModelByFieldAfterSave(ShopCmsContentElement &$cmsContentElement, $fieldName, $value)
    {
        if (strpos("field_".$fieldName, 'shop.')) {
            $realName = str_replace("shop.", "", $fieldName);
            if ($realName == 'quantity') {
                $value = (float)$value;
            }

            $shopProduct = $cmsContentElement->shopProduct;
            $shopProduct->{$realName} = $value;


            if (!$shopProduct->save()) {
                throw new Exception('Свойство магазина не сохранено: '.Json::encode($shopProduct->errors));
            }

        } else if (strpos("field_".$fieldName, 'priceCurrency.')) {
            $priceTypeId = str_replace("priceCurrency.", "", $fieldName);
            $shopProduct = $cmsContentElement->getShopProduct()->one();

            $price = $shopProduct->getShopProductPrices()->andWhere(['type_price_id' => $priceTypeId])->one();

            /**
             * @var ShopProductPrice $price
             */
            if ($price) {
                $price->currency_code = $value;
            } else {
                $price = new ShopProductPrice();
                $price->type_price_id = $priceTypeId;
                $price->product_id = $shopProduct->id;
                $price->currency_code = $value;
            }

            if (!$price->save()) {
                throw new Exception('Цена не сохранена: '.Json::encode($price->errors));
            }

        } else if (strpos("field_".$fieldName, 'priceValue.')) {
            $priceTypeId = str_replace("priceValue.", "", $fieldName);
            $shopProduct = $cmsContentElement->getShopProduct()->one();

            $price = $shopProduct->getShopProductPrices()->andWhere(['type_price_id' => $priceTypeId])->one();

            $value = trim(str_replace(",", ".", $value));
            $value = str_replace(" ", "", $value);

            /**
             * @var ShopProductPrice $price
             */
            if ($price) {
                $price->price = (float)$value;
            } else {
                $price = new ShopProductPrice();
                $price->type_price_id = $priceTypeId;
                $price->product_id = $shopProduct->id;
                $price->currency_code = "RUB";
                $price->price = (float)$value;
            }

            if (!$price->save()) {
                throw new Exception('Цена не сохранена: '.Json::encode($price->errors));
            }
        }
    }

    /**
     * @param $number
     * @param $row
     *
     * @return CsvImportRowResult
     */
    public function import($number, $row)
    {
        $result = new CsvImportRowResult();
        /**
         * @var $element ShopCmsContentElement
         */
        try {
            $isUpdate = false;
            $element = $this->_initElement($number, $row, $this->content_id, ShopCmsContentElement::className());
            $this->_initElementData($number, $row, $element);

            $isUpdate = $element->isNewRecord ? false : true;

            if (!$element->save()) {
                throw new Exception("Ошибка сохранения данных элемента: ".print_r($element->errors, true));
            }
            if (!$element->relatedPropertiesModel->save()) {
                throw new Exception("Ошибка сохранения данных свойств элемента: ".print_r($element->errors, true));
            }

            //Тут работа с данными по товару
            $shopProduct = $element->shopProduct;
            if (!$shopProduct) {
                $shopProduct = new ShopProduct();
                $shopProduct->baseProductPriceValue = 0;
                $shopProduct->baseProductPriceCurrency = "RUB";
                $shopProduct->id = $element->id;
                $shopProduct->product_type = ShopProduct::TYPE_OFFERS;
                $shopProduct->save();
            }
            $element->refresh();
            foreach ($this->matching as $number => $fieldName) {
                $is_update_rewrite = true;

                if ($fieldName) {
                    if (is_array($fieldName)) {
                        $is_update_rewrite = ArrayHelper::getValue($fieldName, 'is_update_rewrite');
                        $fieldName = $fieldName['code'];
                    }

                    if (!$isUpdate) {
                        $this->_initModelByFieldAfterSave($element, $fieldName, $row[$number]);
                    } else {
                        if ($is_update_rewrite) {
                            $this->_initModelByFieldAfterSave($element, $fieldName, $row[$number]);
                        }
                    }

                }
            }

            
            $this->_initImages($element, $row);
            
            $result->data = $this->matching;
            $result->message = ($isUpdate === true ? "Элемент обновлен" : 'Элемент создан');

            $element->relatedPropertiesModel->initAllProperties();
            $rp = Json::encode($element->relatedPropertiesModel->toArray());
            $rp = '';
            $result->html = <<<HTML
Элемент: <a href="{$element->url}" data-pjax="0" target="_blank">{$element->id}</a> $rp
HTML;
            //unset($element->relatedPropertiesModel);
            unset($element);


        } catch (\Exception $e) {
            $result->success = false;
            $result->message = $e->getMessage();
            
            if (!$element->isNewRecord) {
                 $result->html = <<<HTML
Элемент: <a href="{$element->url}" data-pjax="0" target="_blank">{$element->id}</a>
HTML;
            }
        }


        return $result;
    }

}