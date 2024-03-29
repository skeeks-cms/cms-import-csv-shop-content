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
use skeeks\cms\models\CmsContentElement;
use skeeks\cms\models\CmsContentProperty;
use skeeks\cms\shop\models\ShopCmsContentElement;
use skeeks\cms\shop\models\ShopProduct;
use skeeks\cms\shop\models\ShopProductPrice;
use skeeks\cms\shop\models\ShopStore;
use skeeks\cms\shop\models\ShopStoreProduct;
use yii\base\Exception;
use yii\db\ActiveQuery;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;
use yii\helpers\VarDumper;
use yii\widgets\ActiveForm;

/**
 * @property ShopStore $shopStore
 *
 * @author Semenov Alexander <semenov@skeeks.com>
 */
class ImportCsvShopContentHandler extends ImportCsvContentHandler
{

    /**
     * @var null Склад
     */
    public $shop_store_id = null;


    /**
     * @var bool Сохранять исодные данные по товару?
     */
    public $is_save_source_data = false;

    /**
     * @var bool Обнулить количество у файлов которых нет в файле?
     */
    public $is_quantity_clean = false;


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
            if (in_array($key, [
                'quantity',
                'weight',

                'measure_code',
                'measure_ratio',

                'width',
                'length',
                'height',

                'barcodes',
            ])) {
                $fields['shop.'.$key] = $name.' [магазин]';
            }
        }

        foreach (\Yii::$app->shop->shopTypePrices as $price) {
            $fields['priceValue.'.$price->id] = $price->name.' [Значение цены]';
            $fields['priceCurrency.'.$price->id] = $price->name.' [Валюта цены]';
        }

        $fields['measure.symbol'] = 'Символ единицы измерения';

        return $fields;
    }

    public function rules()
    {
        return ArrayHelper::merge(parent::rules(), [
            [['shop_store_id'], 'integer'],
            ['is_save_source_data', 'boolean'],
            ['is_quantity_clean', 'boolean'],
        ]);
    }


    public function attributeLabels()
    {
        return ArrayHelper::merge(parent::attributeLabels(), [
            'shop_store_id'       => 'Поставка/Склад',
            'is_save_source_data' => 'Сохранять исходные данные по товару?',
            'is_quantity_clean'   => 'Обнулить количество у товаров которых нет в файле?',
        ]);
    }

    public function attributeHints()
    {
        return ArrayHelper::merge(parent::attributeHints(), [
            'shop_store_id'       => 'Наличие будет указано на этом складе',
            'is_save_source_data' => 'Если выбрано да, то все исходные данные по товару будут сохранены в специальное поле.',
        ]);
    }


    /**
     * @param ActiveForm $form
     */
    public function renderConfigForm(ActiveForm $form)
    {
        $this->renderCsvConfigForm($form);

        if (\Yii::$app->skeeks->site->shopStores) {
            echo $form->field($this, 'shop_store_id')->listBox(
                ArrayHelper::merge(['' => ' - '], ArrayHelper::map(
                    \Yii::$app->skeeks->site->shopStores, 'id', 'asText'
                )),
                [
                    'size'             => 1,
                    'data-form-reload' => 'true',
                ]);
        }

        echo $form->field($this, 'content_id')->listBox(
            ArrayHelper::merge(['' => ' - '], [
                \Yii::$app->shop->contentProducts->id => \Yii::$app->shop->contentProducts->name
            ]), [
            'size'             => 1,
            'data-form-reload' => 'true',
        ]);

        echo $form->field($this, 'new_elements_is_active')->listBox(\Yii::$app->formatter->booleanFormat, [
            'size' => 1,
        ]);
        echo $form->field($this, 'is_save_source_data')->listBox(\Yii::$app->formatter->booleanFormat, [
            'size' => 1,
        ]);
        echo $form->field($this, 'is_quantity_clean')->listBox(\Yii::$app->formatter->booleanFormat, [
            'size' => 1,
        ]);
        echo $form->field($this, 'titles_row_number');

        if ($this->content_id && $this->rootFilePath && file_exists($this->rootFilePath)) {
            echo $form->field($this, 'matching')->widget(
                \skeeks\cms\importCsv\widgets\MatchingInput::className(),
                [
                    'columns' => $this->getAvailableFields(),
                ]
            );
        }
    }


    protected function _initModelByFieldAfterSave(ShopCmsContentElement &$cmsContentElement, $fieldName, $value)
    {
        if (strpos("field_".$fieldName, 'shop.')) {
            $shopProduct = $cmsContentElement->shopProduct;

            $realName = str_replace("shop.", "", $fieldName);
            if ($realName == 'quantity') {
                $value = (float)$value;

                if ($this->shop_store_id) {

                    $shopStoreProduct = $shopProduct->getShopStoreProducts()->andWhere(['shop_store_id' => $this->shop_store_id])->one();
                    if (!$shopStoreProduct) {
                        $shopStoreProduct = new ShopStoreProduct();
                        $shopStoreProduct->shop_store_id = $this->shop_store_id;
                        $shopStoreProduct->shop_product_id = $shopProduct->id;
                    }

                    $shopStoreProduct->quantity = $value;
                    if (!$shopStoreProduct->save()) {
                        throw new Exception("Не сохранилось количество на складе: {$value} ".Json::encode($shopStoreProduct->errors));
                    }

                }
            }


            $shopProduct->{$realName} = $value;


            /*if (!$shopProduct->save()) {
                throw new Exception('Свойство магазина не сохранено: '.Json::encode($shopProduct->errors));
            }*/

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
            $value = str_replace(" ", "", $value);

            /**
             * @var ShopProductPrice $price
             */
            if ($price) {
                $price->price = (float)$value;
            } else {
                $price = new ShopProductPrice();
                $price->type_price_id = $priceTypeId;
                $price->product_id = $shopProduct->id;
                $price->price = (float)$value;
            }

            if (!$price->save()) {
                throw new Exception('Цена не сохранена: '.Json::encode($price->errors));
            }
        } else if (strpos("field_".$fieldName, 'measure.')) {
            $fieldName = str_replace("measure.", "", $fieldName);
            $measure = \Yii::$app->measureClassifier->getMeasureBySymbol($value);
            $cmsContentElement->shopProduct->measure_code = $measure->code;

            /*if (!$cmsContentElement->shopProduct->save()) {
                throw new Exception('Свойство магазина не сохранено: '.Json::encode($shopProduct->errors));
            }*/
        }
    }


    /**
     * Инициализация элемента по строке из файла импорта
     * Происходит его создание поиск по базе
     *
     * @param      $number
     * @param      $row
     * @param null $contentId
     * @param null $className
     *
     * @return CmsContentElement
     *
     * @throws Exception
     */
    protected function _initElement($number, $row, $contentId = null, $className = null)
    {
        if (!$contentId) {
            $contentId = $this->content_id;
        }

        if (!$className) {
            $className = CmsContentElement::className();
        }


        if (!$this->unique_field) {
            $element = new $className();
            $element->content_id = $contentId;
        } else {
            $uniqueValue = trim($this->getValue($this->unique_field, $row));

            if ($uniqueValue) {
                if (strpos("field_".$this->unique_field, 'element.')) {
                    $realName = str_replace("element.", "", $this->unique_field);
                    $element = ShopCmsContentElement::find()
                        ->where([$realName => $uniqueValue])
                        ->andWhere(["cms_site_id" => \Yii::$app->skeeks->site->id])
                        ->andWhere(["content_id" => $this->content_id])
                        ->one();

                } else if (strpos("field_".$this->unique_field, 'shop.')) {
                    $elementQuery = ShopCmsContentElement::find()->joinWith('shopProduct as shopProduct')
                        ->andWhere(['shopProduct.supplier_external_id' => $uniqueValue]);

                    /*if ($this->shop_supplier_id) {
                        $elementQuery->andWhere(['shopProduct.shop_supplier_id' => $this->shop_supplier_id]);
                    }*/

                    $element = $elementQuery->one();

                } else if (strpos("field_".$this->unique_field, 'property.')) {


                    $realName = str_replace("property.", "", $this->unique_field);

                    /**
                     * @var $property CmsContentProperty
                     */
                    $property = CmsContentProperty::find()->where(['code' => $realName])->one();

                    $query = $className::find();
                    $className::filterByProperty($query, $property, $uniqueValue);

                    $element = $query->one();
                }
            } else {
                throw new Exception('Не задано уникальное значение');
            }

            if (!$element) {
                /**
                 * @var $element CmsContentElement
                 */
                $element = new $className();
                $element->content_id = $contentId;
                if ($this->new_elements_is_active) {
                    $element->active = "Y";
                } else {
                    $element->active = "N";
                }
            }
        }

        return $element;
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
        $element = '';
        try {
            $isUpdate = false;
            $element = $this->_initElement($number, $row, $this->content_id, ShopCmsContentElement::class);
            $this->_initElementData($number, $row, $element);

            $isUpdate = $element->isNewRecord ? false : true;

            if (!$element->save()) {
                throw new Exception("Ошибка сохранения данных элемента: ".print_r($element->errors, true));
            }

            if (!$element->relatedPropertiesModel->save()) {
                throw new Exception("Ошибка сохранения данных свойств элемента: ".print_r($element->relatedPropertiesModel->errors, true).print_r($element->relatedPropertiesModel->toArray(), true));
            }

            //Тут работа с данными по товару
            $shopProduct = $element->shopProduct;
            if (!$shopProduct) {
                $shopProduct = new ShopProduct();
                $shopProduct->quantity = 0;
                $shopProduct->baseProductPriceValue = 0;
                $shopProduct->baseProductPriceCurrency = \Yii::$app->money->currencyCode;
                $shopProduct->id = $element->id;
                //$shopProduct->product_type = ShopProduct::TYPE_OFFERS;

                $shopProductIsUpdate = true;

                if (!$shopProduct->save()) {
                    throw new Exception("Ошибка сохранения shopProduct: ".print_r($shopProduct->errors, true));
                }

                unset($shopProduct);
            }


            $element->refresh();

            $shopProductIsUpdate = false;

            foreach ($this->matching as $number => $fieldName) {
                $is_update_rewrite = true;

                if ($fieldName) {
                    if (is_array($fieldName)) {
                        $is_update_rewrite = ArrayHelper::getValue($fieldName, 'is_update_rewrite');
                        $fieldName = $fieldName['code'];
                    }

                    if (!$isUpdate) {
                        $this->_initModelByFieldAfterSave($element, $fieldName, $row[$number]);
                        $shopProductIsUpdate = true;
                    } else {
                        if ($is_update_rewrite) {
                            $this->_initModelByFieldAfterSave($element, $fieldName, $row[$number]);
                            $shopProductIsUpdate = true;
                        }
                    }

                }
            }


            if ($this->is_save_source_data) {
                $data = $this->getRowDataWithHeaders($row);
                $shopProductIsUpdate = true;
                $element->shopProduct->supplier_external_jsondata = $data;
            }

            if ($shopProductIsUpdate) {

                /*if ($this->shop_supplier_id) {
                    $element->shopProduct->shop_supplier_id = $this->shop_supplier_id;
                }*/

                if (!$element->shopProduct->save()) {
                    throw new Exception('Свойство магазина не сохранено: '.Json::encode($element->shopProduct->errors));
                }

            }


            $this->_initImages($element, $row);

            $result->data = $this->matching;
            $result->message = ($isUpdate === true ? "Элемент обновлен " : 'Элемент создан ');

            //$element->relatedPropertiesModel->initAllProperties();
            //print_r($element->relatedPropertiesModel->toArray());die;
            //$rp = Json::encode($element->relatedPropertiesModel->toArray());
            $rp = '';
            $result->html = <<<HTML
Товар: <a href="{$element->url}" data-pjax="0" target="_blank">{$element->id}</a>
HTML;
            //unset($element->relatedPropertiesModel);
            /*foreach ($element->relatedPropertiesModel->properties as $property)
            {
                unset($property);
            }*/


        } catch (\Exception $e) {
            $result->success = false;
            $result->message = $e->getMessage();
            $result->message = VarDumper::dumpAsString($e, 5);

            if ($element && !$element->isNewRecord) {
                $result->html = <<<HTML
Товар: <a href="{$element->url}" data-pjax="0" target="_blank">{$element->id}</a>
HTML;
            }
        }

        unset($element->cmsSite);
        unset($element->shopProduct);
        unset($element);


        return $result;
    }


    /**
     * @return ShopStore
     */
    public function getShopStore()
    {
        $query = ShopStore::find()->where(['id' => $this->shop_store_id])->one();
        return $query;
    }


    /**
     * @return bool
     */
    public function beforeExecute()
    {
        //Если задан склад и нужно удалять наличие которых нет в файле
        if ($this->is_quantity_clean) {
            if ($this->shop_store_id) {
                if ($updated = ShopStoreProduct::updateAll(['quantity' => 0], ['shop_store_id' => $this->shop_store_id])) {
                    $this->getResult()->stdout("Обнулено: ".$updated);
                }
            }

            /*if ($updated = ShopProduct::updateAll(['quantity' => 0], ['shop_store_id' => $this->shop_store_id])) {
                $this->getResult()->stdout("Обнулено: ".$updated);
            }*/
        }

        return true;
    }

}