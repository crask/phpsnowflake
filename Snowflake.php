<?php
class Snowflake
{

    protected static $WORKERID_BITS = 5;
    protected static $DATACENTERID_BITS = 5;
    protected static $SEQUENCE_BITS = 12;
    protected static $EPOCH = 1288834974657;

    private $workerid;
    private $datacenterid;
    private $sequence = 0;
    private $last_timestamp = 1;

    public function __construct($workerId = null, $datacenterId = null)
    {

        $maxWorkerId = -1 ^ (-1 << self::$WORKERID_BITS);
        $maxDataCenterId = -1 ^ (-1 << self::$DATACENTERID_BITS);

        if(!$workerId && !is_numeric($workerId)){
            $workerId = $this->auto_get_worker_id();
        }

        if(!$datacenterId && !is_numeric($workerId)) {
            $datacenterId=$this->auto_get_datacenter_id();
        }

        $workerId = floor($workerId);
        $datacenterId = floor($datacenterId);

        if ($workerId > $maxWorkerId || $workerid < 0)
        {
            trigger_error(sprintf("workerid %d can't be greater than %d or less than 0", $workerId,$maxWorkerId));
            return false;
        }

        if ($datacenterId > $maxDataCenterId || $datacenerid < 0)
        {
            trigger_error(sprintf("datacenterid %d can't be greater than %d or less than 0", $datacenterId,$maxDataCenterId));
            return false;
        }

        $this->workerid = $workerId;
        $this->datacenterid = $datacenterId;
    }


    public function get_next_id(){
        if(function_exists('apc_add') &&  ini_get('apc.enabled')){
            return $this->get_nextid_apc();
        }else{
            return $this->get_nextid_local();
        }
    }

    protected function get_nextid_local()
    {
        $timestamp = $this->get_timestamp();
        if ($timestamp < $this->last_timestamp)
        {
            trigger_error(sprintf("Clock is moving backwards. Rejecting requests until %d.", $timestamp));
            return false;
        }
        if ($timestamp == $this->last_timestamp)
        {
            $this->sequence = $this->sequence + 1 & SEQUENCE_MASK;
            if ($this->sequence == 0)
            {
                $next_timestamp = $this->get_timestamp();
                while ($next_timestamp <= $timestamp)
                {
                    $next_timestamp = $this->get_timestamp();
                }
                $timestamp = $next_timestamp;
            }
        }
        else
        {
            $this->sequence = 0;
        }
        $this->last_timestamp = $timestamp;

        $workerIdShift= static::$SEQUENCE_BITS;
        $datacenterIdShift = static::$SEQUENCE_BITS + static::$WORKERID_BITS;
        $timestampShift = static::$SEQUENCE_BITS + static::$WORKERID_BITS + static::$DATACENTERID_BITS;

        return (($this->last_timestamp - static::$EPOCH) << $timestampShift) |
                ($this->datacenterid << $datacenterIdShift) |
                ($this->workerid << $workerIdShift) |
                $this->sequence;
    }

    protected function get_nextid_apc()
    {
        $timestamp = $this->get_timestamp();
        if ($timestamp < $this->last_timestamp)
        {
            trigger_error(sprintf("Clock is moving backwards. Rejecting requests until %d.", $timestamp));
            return false;
        }

        $apc_key = sprintf("phpsnowflake_%s_%s_%d",$this->datacenterid,$this->workerid,$timestamp);

        if(!apc_exists($apc_key)){
            apc_add($apc_key,0,60);
        }

        $sequence = apc_inc($apc_key);

        if(!$sequence){
            return false;
        }

        $maxSequence = -1 ^ (-1 << static::$SEQUENCE_BITS) ;
        if($sequence >= $maxSequence){
            usleep(1);
            return $this->get_nextid_apc();
        }

        $this->sequence = $sequence;
        $this->last_timestamp = $timestamp;

        $workerIdShift= static::$SEQUENCE_BITS;
        $datacenterIdShift = static::$SEQUENCE_BITS + static::$WORKERID_BITS;
        $timestampShift = static::$SEQUENCE_BITS + static::$WORKERID_BITS + static::$DATACENTERID_BITS;

        return (($timestamp - static::$EPOCH) << $timestampShift) |
                ($this->datacenterid << $datacenterIdShift) |
                ($this->workerid << $workerIdShift) |
                $sequence;

    }


    protected function get_timestamp()
    {
        return floor(microtime(true) * 1000);
    }

    protected function auto_get_worker_id(){
        $workerId=null;
        $maxWorkerId = -1 ^ (-1 << self::$WORKERID_BITS);

        if($workerId===null){
            $workerId=$_SERVER['SNOWFLAKE_WORKER_ID'];
        }
        if($workerId===null){
            $workerId=getmypid();
        }

        $workerId=intval($workerId);
        $workerId = $workerId % $maxWorkerId;

        return $workerId;
    }

    protected function auto_get_datacenter_id(){
        $datacenterId=null;
        $maxDatacenterId = -1 ^ (-1 << self::$DATACENTERID_BITS);

        if($datacenterId===null){
            $datacenterId=$_SERVER['SNOWFLAKE_DATACENTER_ID'];
        }
        if($datacenterId===null){
            $datacenterId=ip2long($_SERVER['SERVER_ADDR']);
        }

        $datacenterId=intval($datacenterId);
        $datacenterId = $datacenterId % $maxDatacenterId;

    }

}

?>