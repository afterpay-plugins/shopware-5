<?php

namespace AfterPay\Models;

use Doctrine\ORM\Mapping as ORM;
use Shopware\Components\Model\ModelEntity;

/**
 * @ORM\Entity
 * @ORM\Table(name="s_plugin_afterpay_transactions")
 */
class Transactions extends ModelEntity
{

    /**
     * @var integer $id
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * @var integer $orderId
     *
     * @ORM\Column(name="order_id", type="integer", length=11, nullable=false)
     */
    protected $orderId;

    /**
     * @var string $number
     *
     * @ORM\Column(name="number", type="string", length=255, nullable=false)
     */
    protected $number;

    /**
     * @var float $amount
     *
     * @ORM\Column(name="amount", type="float", nullable=true)
     */
    protected $amount;

    /**
     * @var integer $status
     *
     * @ORM\Column(name="status", type="integer", length=11, nullable=true)
     */
    protected $status;

    /**
     * @var \DateTime $date
     *
     * @ORM\Column(name="date", type="datetime", nullable=false)
     */
    protected $date;

    /**
     * Class constructor.
     */
    public function __construct()
    {
        $this->date = new \DateTime();
    }

    /**
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param $id
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return integer
     */
    public function getOrderId()
    {
        return $this->orderId;
    }

    /**
     * @param integer $orderId
     * @return $this
     */
    public function setOrderId($orderId)
    {
        $this->orderId = $orderId;
        return $this;
    }

    /**
     * @return string
     */
    public function getNumber()
    {
        return $this->number;
    }

    /**
     * @param string $number
     * @return $this
     */
    public function setNumber($number)
    {
        $this->number = $number;
        return $this;
    }

    /**
     * @return float
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * @param float $amount
     * @return $this
     */
    public function setAmount($amount)
    {
        $this->amount = $amount;
        return $this;
    }

    /**
     * @return integer
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param integer $status
     * @return $this
     */
    public function setStatus($status)
    {
        $this->status = $status;
        return $this;
    }

    /**
     * @return string
     */
    public function getDate()
    {
        return $this->date;
    }

    /**
     * @param \DateTime $date
     * @return $this
     */
    public function setData($date)
    {
        $this->date = $date;
        return $this;
    }

}
