<?php

/**
 * This file is part of the Money library.
 *
 * Copyright (c) 2011-2014 Mathias Verraes
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Money;

use InvalidArgumentException;
use MoneyMath\Decimal2;
use Nette\Object;
use OverflowException;
use UnderflowException;

/**
 * Money Value Object
 *
 * @author Mathias Verraes
 */
class MoneyWithoutCurrency extends Object {

    /**
     * Internal value
     *
     * @var Decimal2
     */
    private $amount;

    /**
     * @param int|Decimal2  $amount   Amount, expressed in the smallest units of $currency (eg cents)
     * @param Currency $currency
     *
     * @throws InvalidArgumentException If amount is not integer
     */
    public function __construct($amount) {
        $this->amount = Decimal2::from($amount);
    }

    public static function from($amount){
        return new MoneyWithoutCurrency($amount);
    }

    /**
     * Returns a new Money instance based on the current one using the Currency
     *
     * @param int|Decimal2 $amount
     *
     * @return Money
     */
    private function newInstance($amount) {
        return new MoneyWithoutCurrency($amount);
    }

    /**
     * Asserts that a Money has the same currency as this
     *
     * @throws InvalidArgumentException If $other has a different currency
     */

    /**
     * Checks whether the value represented by this object equals to the other
     *
     * @param Money $other
     *
     * @return boolean
     */
    public function equals(MoneyWithoutCurrency $other) {
        return $this->amount->compare($other->amount) == 0;
    }

    /**
     * Returns an integer less than, equal to, or greater than zero
     * if the value of this object is considered to be respectively
     * less than, equal to, or greater than the other
     *
     * @param Money $other
     *
     * @return int
     */
    public function compare(MoneyWithoutCurrency $other) {
        $result = $this->amount->compare($other->amount);
        if ($result < 0) {
            return -1;
        } elseif ($result) {
            return 0;
        } else {
            return 1;
        }
    }

    /**
     * Checks whether the value represented by this object is greater than the other
     *
     * @param Money $other
     *
     * @return boolean
     */
    public function greaterThan(MoneyWithoutCurrency $other) {
        return 1 == $this->compare($other);
    }

    /**
     * Checks whether the value represented by this object is less than the other
     *
     * @param Money $other
     *
     * @return boolean
     */
    public function lessThan(MoneyWithoutCurrency $other) {
        return -1 == $this->compare($other);
    }

    /**
     * Returns the value represented by this object, only integer part, without cents.
     *
     * @return int
     */
    public function getAmount() {
        return $this->amount->integerValue();
    }

    /**
     * Returns a new Money object that represents
     * the sum of this and an other Money object
     *
     * @param Money $addend
     *
     * @return Money
     */
    public function add(MoneyWithoutCurrency $addend) {
        $amount = $this->amount->add($addend->amount);
        return $this->newInstance($amount);
    }

    /**
     * Returns a new Money object that represents
     * the difference of this and an other Money object
     *
     * @param Money $subtrahend
     *
     * @return Money
     */
    public function subtract(MoneyWithoutCurrency $subtrahend) {
        $amount = $this->amount->subtract($subtrahend->amount);
        return $this->newInstance($amount);
    }

    /**
     * Asserts that the operand is integer or float
     *
     * @throws InvalidArgumentException If $operand is neither integer nor float
     */
    private function assertOperand($operand) {
        if (!is_int($operand)) {
            throw new InvalidArgumentException('Operand should be an integer');
        }
    }

    /**
     * Returns a new Money object that represents
     * the multiplied value by the given factor
     *
     * @param numeric $multiplier
     * @param int $roundingMode
     *
     * @return Money
     */
    public function multiply($multiplier) {
        $this->assertOperand($multiplier);
        $product = $this->amount->multiplyBy($multiplier);
        return $this->newInstance($product);
    }

    /**
     * Returns a new Money object that represents
     * the divided value by the given factor
     *
     * @param numeric $divisor
     * @param int $roundingMode
     *
     * @return Money
     */
    public function divide($divisor) {
        $this->assertOperand($divisor);
        $quotient = $this->amount->divide($divisor);
        return $this->newInstance($quotient);
    }

    /**
     * Allocate the money according to a list of ratios
     *
     * @param array $ratios
     *
     * @return Money[]
     */
    public function allocate(array $ratios) {
        $remainder = $this->amount;
        $results = array();
        $total = array_sum($ratios);

        foreach ($ratios as $ratio) {
            $share = $this->amount->multiplyBy($ratio)->divide(Decimal2::from($total));
            $results[] = new MoneyWithoutCurrency($share);
            $remainder = $remainder->subtract($share);
        }
        for ($i = 0; $remainder > 0; $i++) {
            $results[$i] = $results[$i]->amount->add(1);
            $remainder = $remainder->subtract(1);
        }
        return $results;
    }

    /**
     * Allocate the money among N targets
     *
     * @param int $n
     *
     * @return Money[]
     *
     * @throws InvalidArgumentException If number of targets is not an integer
     */
    public function allocateTo($n) {
        if (!is_int($n)) {
            throw new InvalidArgumentException('Number of targets must be an integer');
        }
        $amount = $this->amount->divide($n);
        $results = array();

        for ($i = 0; $i < $n; $i++) {
            $results[$i] = $this->newInstance($amount);
        }

        for ($i = 0; $i < $this->amount->modulo($n); $i++) {
            $results[$i]->amount = $results[$i]->amount->add(1);
        }

        return $results;
    }

    /**
     * Checks if the value represented by this object is zero
     *
     * @return boolean
     */
    public function isZero() {
        return $this->amount->isZero();
    }

    /**
     * Checks if the value represented by this object is positive
     *
     * @return boolean
     */
    public function isPositive() {
        return $this->amount->isPositive();
    }

    /**
     * Checks if the value represented by this object is negative
     *
     * @return boolean
     */
    public function isNegative() {
        return $this->amount->isNegative();
    }

    /**
     * Creates units from string
     *
     * @param string $string
     *
     * @return int
     *
     * @throws InvalidArgumentException If $string cannot be parsed
     */
    public static function stringToUnits($string) {
        $sign = "(?P<sign>[-\+])?";
        $digits = "(?P<digits>\d*)";
        $separator = "(?P<separator>[.,])?";
        $decimals = "(?P<decimal1>\d)?(?P<decimal2>\d)?";
        $pattern = "/^".$sign.$digits.$separator.$decimals."$/";

        if (!preg_match($pattern, trim($string), $matches)) {
            throw new InvalidArgumentException("The value could not be parsed as money");
        }

        $units = $matches['sign'] == "-" ? "-" : "";
        $units .= $matches['digits'];
        $units .= isset($matches['decimal1']) ? $matches['decimal1'] : "0";
        $units .= isset($matches['decimal2']) ? $matches['decimal2'] : "0";

        return (int) $units;
    }

    public function __toString(){
        return $this->amount->__toString();
    }
}
