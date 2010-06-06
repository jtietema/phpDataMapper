<?PHP
class phpDataMapper_Property_String extends phpDataMapper_Property
{
  protected function fieldDefaults()
  {
    return array_merge(parent::fieldDefaults(), array(
      'length' => 255
    ));
  }
}
