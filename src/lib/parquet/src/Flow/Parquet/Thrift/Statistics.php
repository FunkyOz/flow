<?php declare(strict_types=1);
namespace Flow\Parquet\Thrift;

/**
 * Autogenerated by Thrift Compiler (0.19.0).
 *
 * DO NOT EDIT UNLESS YOU ARE SURE THAT YOU KNOW WHAT YOU ARE DOING
 *
 *  @generated
 */
use Thrift\Base\TBase;
use Thrift\Type\TType;

/**
 * Statistics per row group and per page
 * All fields are optional.
 */
class Statistics extends TBase
{
    public static $_TSPEC = [
        1 => [
            'var' => 'max',
            'isRequired' => false,
            'type' => TType::STRING,
        ],
        2 => [
            'var' => 'min',
            'isRequired' => false,
            'type' => TType::STRING,
        ],
        3 => [
            'var' => 'null_count',
            'isRequired' => false,
            'type' => TType::I64,
        ],
        4 => [
            'var' => 'distinct_count',
            'isRequired' => false,
            'type' => TType::I64,
        ],
        5 => [
            'var' => 'max_value',
            'isRequired' => false,
            'type' => TType::STRING,
        ],
        6 => [
            'var' => 'min_value',
            'isRequired' => false,
            'type' => TType::STRING,
        ],
    ];

    public static $isValidate = false;

    /**
     * count of distinct values occurring.
     *
     * @var int
     */
    public $distinct_count;

    /**
     * DEPRECATED: min and max value of the column. Use min_value and max_value.
     *
     * Values are encoded using PLAIN encoding, except that variable-length byte
     * arrays do not include a length prefix.
     *
     * These fields encode min and max values determined by signed comparison
     * only. New files should use the correct order for a column's logical type
     * and store the values in the min_value and max_value fields.
     *
     * To support older readers, these may be set when the column order is
     * signed.
     *
     * @var string
     */
    public $max;

    /**
     * Min and max values for the column, determined by its ColumnOrder.
     *
     * Values are encoded using PLAIN encoding, except that variable-length byte
     * arrays do not include a length prefix.
     *
     * @var string
     */
    public $max_value;

    /**
     * @var string
     */
    public $min;

    /**
     * @var string
     */
    public $min_value;

    /**
     * count of null value in the column.
     *
     * @var int
     */
    public $null_count;

    public function __construct($vals = null)
    {
        if (\is_array($vals)) {
            parent::__construct(self::$_TSPEC, $vals);
        }
    }

    public function getName()
    {
        return 'Statistics';
    }

    public function read($input)
    {
        return $this->_read('Statistics', self::$_TSPEC, $input);
    }

    public function write($output)
    {
        return $this->_write('Statistics', self::$_TSPEC, $output);
    }
}
