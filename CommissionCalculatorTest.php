<?php

use PHPUnit\Framework\TestCase;

require_once 'vendor/autoload.php';
require_once './CommissionCalculator.php';

class CommissionCalculatorTest extends TestCase
{
    private $commissionCalculator; 
    private $currencyRateProvider;
    private $binProvider;

    protected function setUp(): void
    {
        $this->currencyRateProvider = $this->createMock(CurrencyRateProvider::class);
        $this->binProvider = $this->createMock(BinProvider::class);

        $euCountries = ['AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PO', 'PT', 'RO', 'SE', 'SI', 'SK'];
        $this->commissionCalculator = new CommissionCalculator($this->currencyRateProvider, $this->binProvider, $euCountries);
    }

    public function testCalculateCommissionForEuCountry()
    {
        $bin = '123456';
        $amount = 100;
        $currency = 'EUR';

        $binData = new stdClass();
        $binData->country = new stdClass();
        $binData->country->alpha2 = 'LT';

        $this->binProvider->expects($this->once())
            ->method('getBinData')
            ->with($this->equalTo($bin))
            ->will($this->returnValue($binData));

        $this->currencyRateProvider->expects($this->once())
            ->method('getRates')
            ->with($this->equalTo('EUR'))
            ->will($this->returnValue(['rates' => ['EUR' => 1]]));

        $commission = $this->commissionCalculator->calculateCommission($bin, $amount, $currency);
        $this->assertEquals(1, $commission);
    }

    public function testCalculateCommissionForNonEuCountry()
    {
        $bin = '123456';
        $amount = 100;
        $currency = 'EUR';

        $binData = new stdClass();
        $binData->country = new stdClass();
        $binData->country->alpha2 = 'US';

        $this->binProvider->expects($this->once())
            ->method('getBinData')
            ->with($this->equalTo($bin))
            ->will($this->returnValue($binData));

        $this->currencyRateProvider->expects($this->once())
            ->method('getRates')
            ->with($this->equalTo('EUR'))
            ->will($this->returnValue(['rates' => ['EUR' => 1]]));

        $commission = $this->commissionCalculator->calculateCommission($bin, $amount, $currency);
        $this->assertEquals(2, $commission);
    }
}