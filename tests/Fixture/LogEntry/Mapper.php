<?PHP
class Fixture_LogEntry_Mapper extends phpDataMapper_Base
{
  protected $_datasource = 'test_log_entry';
  
  public $date = array('type' => 'date', 'primary' => true);
  public $server_ip = array('type' => 'string', 'primary' => true);
  public $info = array('type' => 'string', 'default' => 'Nothing happened');
}
