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
use skeeks\cms\shop\models\ShopContent;
use skeeks\cms\shop\models\ShopProduct;
use yii\db\ActiveQuery;
use yii\helpers\ArrayHelper;
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
     * @param $number
     * @param $row
     *
     * @return CsvImportRowResult
     */
    public function import($number, $row)
    {
        $result = new CsvImportRowResult();

        try
        {
            $isUpdate = false;
            $element = null;

            if (!$this->unique_field)
            {
                $element                = new CmsContentElement();
                $element->content_id    =   $this->content_id;
            } else
            {
                $uniqueValue = trim($this->getValue($this->unique_field, $row));
                if ($uniqueValue)
                {
                    if (strpos("field_" . $this->unique_field, 'element.'))
                    {
                        $realName = str_replace("element.", "", $this->unique_field);
                        $element = CmsContentElement::find()->where([$realName => $uniqueValue])->one();

                    } else if (strpos("field_" . $this->unique_field, 'property.'))
                    {
                        $realName = str_replace("property.", "", $this->unique_field);

                        $element = CmsContentElement::find()

                            ->joinWith('relatedElementProperties map')
                            ->joinWith('relatedElementProperties.property property')

                            ->andWhere(['property.code'     => $realName])
                            ->andWhere(['map.value'         => $uniqueValue])

                            ->joinWith('cmsContent as ccontent')
                            ->andWhere(['ccontent.id'        => $this->content_id])

                            ->one()
                        ;
                    }
                } else
                {
                    throw new Exception('Не задано уникальное значение');
                }

                if (!$element)
                {
                    $element                = new CmsContentElement();
                    $element->content_id    =   $this->content_id;
                } else
                {
                    $isUpdate = true;
                }
            }

            foreach ($this->matching as $number => $fieldName)
            {
                //Выбрано соответствие
                if ($fieldName)
                {
                    $this->_initModelByField($element, $fieldName, $row[$number]);
                }
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
                    try
                    {
                        $file = \Yii::$app->storage->upload($imageUrl, [
                            'name' => $element->name
                        ]);

                        $element->link('image', $file);

                    } catch (\Exception $e)
                    {
                        //\Yii::error('Not upload image to: ' . $cmsContentElement->id . " ({$realUrl})", 'import');
                    }
                }


                $result->message        =   $isUpdate === true ? "Элемент обновлен" : 'Элемент создан' ;

                $rp = Json::encode($element->relatedPropertiesModel->toArray());
                $rp = '';
                $result->html           =   <<<HTML
Элемент: <a href="{$element->url}" data-pjax="0" target="_blank">{$element->id}</a> $rp
HTML;

            } else
            {
                $result->success        =   false;
                $result->message        =   'Ошибка';
                $result->html           =   Json::encode($element->errors) . "<br />" . Json::encode($element->relatedPropertiesModel->errors);
            }

            $result->data           =   $this->matching;


        } catch (\Exception $e)
        {
            $result->success        =   false;
            $result->message        =   $e->getMessage();
        }






        return $result;
    }

}