<?php
/**
 * @author Semenov Alexander <semenov@skeeks.com>
 * @link http://skeeks.com/
 * @copyright 2010 SkeekS (СкикС)
 * @date 29.08.2016
 */
namespace skeeks\cms\importCsvShopContent;
use skeeks\cms\importCsv\helpers\CsvImportRowResult;
use skeeks\cms\importCsvContent\ImportCsvContentHandler;
use skeeks\cms\models\CmsContent;
use skeeks\cms\models\CmsContentElement;
use skeeks\cms\shop\models\ShopCmsContentElement;
use skeeks\cms\shop\models\ShopContent;
use skeeks\cms\shop\models\ShopProduct;
use skeeks\cms\shop\models\ShopProductPrice;
use yii\base\Exception;
use yii\db\ActiveQuery;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;
use yii\widgets\ActiveForm;

/**
 * @property CmsContent $childrenCmsContent
 *
 * Class CsvContentHandler
 *
 * @package skeeks\cms\importCsvContent
 */
class ImportCsvShopContentHandler extends ImportCsvContentHandler
{
    public $matchingChild = [];
    public $parentRelationField = null;

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

        foreach ((new ShopProduct())->attributeLabels() as $key => $name)
        {
            $fields['shop.' . $key] = $name . ' [магазин]';
        }

        foreach (\Yii::$app->shop->shopTypePrices as $price)
        {
            $fields['priceValue.' . $price->id] = $price->name . ' [Значение цены]';
            $fields['priceCurrency.' . $price->id] = $price->name . ' [Валюта цены]';
        }

        return $fields;
    }
    /**
     * @return array
     */
    public function getAvailableChildrenFields()
    {
        $element = new CmsContentElement([
            'content_id' => $this->childrenCmsContent->id
        ]);

        $fields = [];

        foreach ($element->attributeLabels() as $key => $name)
        {
            $fields['element.' . $key] = $name;
        }

        foreach ($element->relatedPropertiesModel->attributeLabels() as $key => $name)
        {
            $fields['property.' . $key] = $name . " [свойство]";
        }

        foreach ((new ShopProduct())->attributeLabels() as $key => $name)
        {
            $fields['shop.' . $key] = $name . ' [магазин]';
        }

        foreach (\Yii::$app->shop->shopTypePrices as $price)
        {
            $fields['priceValue.' . $price->id] = $price->name . ' [Значение цены]';
            $fields['priceCurrency.' . $price->id] = $price->name . ' [Валюта цены]';
        }

        $fields['relation'] = 'Связь с товаром';

        return array_merge(['' => ' - '], $fields);
    }

    /**
     * @return array|null|\yii\db\ActiveRecord
     */
    public function getChildrenCmsContent()
    {
        if ($this->cmsContent)
        {
            return $this->cmsContent->getChildrenContents()
                ->andWhere([
                    'id' => \yii\helpers\ArrayHelper::map(\skeeks\cms\shop\models\ShopContent::find()->all(), 'content_id', 'content_id')
                ])->one();
        }

        return null;
    }

    public function getAvailableChildFields()
    {
        $element = new CmsContentElement([
            'content_id' => $this->cmsContent->childrenContents
        ]);

        $fields = [];

        foreach ($element->attributeLabels() as $key => $name)
        {
            $fields['element.' . $key] = $name;
        }

        foreach ($element->relatedPropertiesModel->attributeLabels() as $key => $name)
        {
            $fields['property.' . $key] = $name . " [свойство]";
        }

        $fields['image'] = 'Ссылка на главное изображение';

        return array_merge(['' => ' - '], $fields);
    }




    public function rules()
    {
        return ArrayHelper::merge(parent::rules(), [

            [['matchingChild'], 'safe'],
            [['parentRelationField'], 'string'],
            [['matchingChild'], function($attribute) {
                /*if (!in_array('element.parent_content_element_id', $this->$attribute))
                {
                    $this->addError($attribute, "Укажите соответствие названия");
                }
                if (!in_array('element.name', $this->$attribute))
                {
                    $this->addError($attribute, "Укажите соответствие названия");
                }*/
            }]
        ]);
    }


    public function attributeLabels()
    {
        return ArrayHelper::merge(parent::attributeLabels(), [
            'matchingChild'        => \Yii::t('skeeks/importCsvShopContent', 'Соответствие торговых предложений'),
            'parentRelationField'  => \Yii::t('skeeks/importCsvShopContent', 'Поле товара для связи'),
        ]);
    }


    /**
     * @param ActiveForm $form
     */
    public function renderConfigForm(ActiveForm $form)
    {
        $this->renderCsvConfigForm($form);

        echo $form->field($this, 'content_id')->listBox(
            array_merge(['' => ' - '], CmsContent::getDataForSelect(true, function(ActiveQuery $activeQuery)
            {
                $activeQuery->andWhere([
                    'id' => \yii\helpers\ArrayHelper::map(\skeeks\cms\shop\models\ShopContent::find()->all(), 'content_id', 'content_id')
                ]);
            })), [
            'size' => 1,
            'data-form-reload' => 'true'
        ]);

        if ($this->content_id && $this->rootFilePath && file_exists($this->rootFilePath))
        {
            echo $form->field($this, 'matching')->widget(
                \skeeks\cms\importCsv\widgets\MatchingInput::className(),
                [
                    'columns' => $this->getAvailableFields()
                ]
            );

            echo $form->field($this, 'unique_field')->listBox(
                array_merge(['' => ' - '], $this->getAvailableFields()), [
                'size' => 1,
            ]);

            if ($this->childrenCmsContent)
            {
                echo $form->field($this, 'matchingChild')->widget(
                    \skeeks\cms\importCsv\widgets\MatchingInput::className(),
                    [
                        'columns' => $this->getAvailableChildrenFields()
                    ]
                );

                echo $form->field($this, 'parentRelationField')->listBox(
                    array_merge(['' => ' - '], $this->getAvailableFields()), [
                    'size' => 1,
                ]);
            }


        }
    }



    /**
     * @param $code
     *
     * @return int|null
     */
    public function getChildrenColumnNumber($code)
    {
        if (in_array($code, $this->matchingChild))
        {
            foreach ($this->matchingChild as $number => $codeValue)
            {
                if ($codeValue == $code)
                {
                    return (int) $number;
                }
            }
        }

        return null;
    }

    /**
     * @param $code
     * @param array $row
     *
     * @return null
     */
    public function getChildrenValue($code, $row = [])
    {
        $number = $this->getChildrenColumnNumber($code);

        if ($number !== null)
        {
            return $row[$number];
        }

        return null;
    }



    protected function _initModelByFieldAfterSave(ShopCmsContentElement &$cmsContentElement, $fieldName, $value)
    {
        if (strpos("field_" . $fieldName, 'shop.'))
        {
            $realName = str_replace("shop.", "", $fieldName);
            $shopProduct = $cmsContentElement->shopProduct;
            $shopProduct->{$realName} = $value;
            $shopProduct->save();

        } else if (strpos("field_" . $fieldName, 'priceCurrency.'))
        {
            $priceTypeId = str_replace("priceCurrency.", "", $fieldName);
            $shopProduct = $cmsContentElement->shopProduct;

            $price = $shopProduct->getShopProductPrices()->andWhere(['type_price_id' => $priceTypeId])->one();

            /**
             * @var ShopProductPrice $price
             */
            if ($price)
            {
                $price->currency_code = $value;
            } else
            {
                $price = new ShopProductPrice();
                $price->type_price_id = $priceTypeId;
                $price->product_id = $shopProduct->id;
                $price->currency_code = $value;
            }

            $price->save();

        } else if (strpos("field_" . $fieldName, 'priceValue.'))
        {
            $priceTypeId = str_replace("priceValue.", "", $fieldName);
            $shopProduct = $cmsContentElement->shopProduct;

            $price = $shopProduct->getShopProductPrices()->andWhere(['type_price_id' => $priceTypeId])->one();

            $value = trim(str_replace(",", ".", $value));

            /**
             * @var ShopProductPrice $price
             */
            if ($price)
            {
                $price->price = $value;
            } else
            {
                $price = new ShopProductPrice();
                $price->type_price_id = $priceTypeId;
                $price->product_id = $shopProduct->id;
                $price->currency_code = "RUB";
                $price->price = $value;
            }

            $price->save();
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
        try
        {
            $isUpdate = false;
            $element = null;

            $isChildren = false;
            //Если есть настроен дочерний контент, если задано поле связи с родителем, и поле задано у родителя
            if ($this->matchingChild && $this->parentRelationField && $this->getChildrenColumnNumber('relation'))
            {
                //Эта строка торговое предложение
                if ($relation = $this->getChildrenValue('relation', $row))
                {
                    $isChildren = true;
                }
            }

            if ($isChildren)
            {
                $isUpdate = false;
                $element = $this->_initElement($number, $row, $this->childrenCmsContent->id, ShopCmsContentElement::className());

                if (!$element->isNewRecord)
                {
                    $isUpdate = true;
                } else
                {
                    //Нужно свзять предложение с товаром
                    $relationValue = $this->getChildrenValue('relation', $row);
                    $parentElement = $this->getElement($this->parentRelationField, $relationValue);
                    if (!$parentElement)
                    {
                        throw new Exception('Торговое предложение, не найден товар к которому привязать.');
                    } else
                    {
                        $element->parent_content_element_id = $parentElement->id;
                        $element->name                      = $parentElement->name;
                    }
                }

                foreach ($this->matchingChild as $number => $fieldName)
                {
                    //Выбрано соответствие
                    if ($fieldName)
                    {
                        $this->_initModelByField($element, $fieldName, $row[$number]);
                    }
                }

                $relation = $this->getChildrenValue('relation', $row);

                $element->validate();
                $element->relatedPropertiesModel->validate();

                if (!$element->errors && !$element->relatedPropertiesModel->errors)
                {
                    $element->save();

                    if (!$element->relatedPropertiesModel->save())
                    {
                        throw new Exception('Не сохранены дополнительные данные');
                    };

                    $imageUrl = $this->getValue('image', $row);
                    if ($imageUrl && !$element->image)
                    {
                        $file = \Yii::$app->storage->upload($imageUrl, [
                            'name' => $element->name
                        ]);

                        $element->link('image', $file);
                    }


                    $shopProduct = $element->shopProduct;
                    if (!$shopProduct)
                    {
                        $shopProduct = new ShopProduct();
                        $shopProduct->baseProductPriceValue = 0;
                        $shopProduct->baseProductPriceCurrency = "RUB";
                        $shopProduct->id = $element->id;
                        $shopProduct->product_type = ShopProduct::TYPE_OFFERS;
                        $shopProduct->save();
                    }

                    foreach ($this->matchingChild as $number => $fieldName)
                    {
                        //Выбрано соответствие
                        if ($fieldName)
                        {
                            $this->_initModelByFieldAfterSave($element, $fieldName, $row[$number]);
                        }
                    }

                    $result->message        =   $isUpdate === true ? "Торговое предложение обновлено" : 'Торговое предложение создано' ;

                    $rp = Json::encode($element->relatedPropertiesModel->toArray());
                    //$rp = '';
                    $result->html           =   <<<HTML
    Товар: <a href="{$element->parentContentElement->url}" data-pjax="0" target="_blank">{$element->parentContentElement->id} (предложение: {$element->id})</a> $rp
HTML;

                } else
                {
                    $result->success        =   false;
                    $result->message        =   'Ошибка';
                    $result->html           =   Json::encode($element->errors) . "<br />" . Json::encode($element->relatedPropertiesModel->errors);
                }


            } else
            {

                $isUpdate = false;
                $element = $this->_initElement($number, $row, $this->content_id, ShopCmsContentElement::className());
                foreach ($this->matching as $number => $fieldName)
                {
                    //Выбрано соответствие
                    if ($fieldName)
                    {
                        $this->_initModelByField($element, $fieldName, $row[$number]);
                    }
                }

                if (!$element->isNewRecord)
                {
                    $isUpdate = true;
                }

                $element->validate();
                $element->relatedPropertiesModel->validate();

                if (!$element->errors && !$element->relatedPropertiesModel->errors)
                {
                    $element->save();

                    if (!$element->relatedPropertiesModel->save())
                    {
                        throw new Exception('Не сохранены дополнительные данные');
                    };

                    $imageUrl = $this->getValue('image', $row);
                    if ($imageUrl && !$element->image)
                    {
                        $file = \Yii::$app->storage->upload($imageUrl, [
                            'name' => $element->name
                        ]);

                        $element->link('image', $file);
                    }

                    $shopProduct = $element->shopProduct;
                    if (!$shopProduct)
                    {
                        $shopProduct = new ShopProduct();
                        $shopProduct->baseProductPriceValue = 0;
                        $shopProduct->baseProductPriceCurrency = "RUB";
                        $shopProduct->id = $element->id;
                        $shopProduct->product_type = ShopProduct::TYPE_OFFERS;
                        $shopProduct->save();
                    }

                    foreach ($this->matching as $number => $fieldName)
                    {
                        //Выбрано соответствие
                        if ($fieldName)
                        {
                            $this->_initModelByFieldAfterSave($element, $fieldName, $row[$number]);
                        }
                    }


                    $result->message        =   $isUpdate === true ? "Товар обновлен" : 'Товар создан' ;

                    $rp = Json::encode($element->relatedPropertiesModel->toArray());
                    $rp = '';
                    $result->html           =   <<<HTML
    Товар: <a href="{$element->url}" data-pjax="0" target="_blank">{$element->id}</a> $rp
HTML;

                } else
                {
                    $result->success        =   false;
                    $result->message        =   'Ошибка';
                    $result->html           =   Json::encode($element->errors) . "<br />" . Json::encode($element->relatedPropertiesModel->errors);
                }
            }




        } catch (\Exception $e)
        {
            $result->success        =   false;
            $result->message        =   $e->getMessage();
        }






        return $result;
    }

}