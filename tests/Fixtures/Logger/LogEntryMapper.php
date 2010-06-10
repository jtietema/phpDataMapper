<?PHP
/**
 * Mapper to test composite primary keys and indexes.
 */
class Fixtures_Logger_LogEntryMapper extends phpDataMapper_Mapper
{
  protected $_dataSource = 'logger_log_entries';
  
  public $date      = array('type' => 'date', 'primary' => true);
  public $server_ip = array('type' => 'string', 'primary' => true);
  public $info      = array('type' => 'string', 'default' => 'Nothing happened');
}
