<?PHP
class phpDataMapper_Property_Integer extends phpDataMapper_Property
{
  protected function fieldDefaults()
  {
    return array_merge(parent::fieldDefaults(), array(
      'length'  => 11,
      'serial'  => false
    ));
  }
}
