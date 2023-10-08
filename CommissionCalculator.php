<?php

interface CurrencyRateProvider
{
    public function getRates($baseCurrency);
}

interface BinProvider
{
    public function getBinData($bin);
}

class ExchangeRatesApi implements CurrencyRateProvider
{
    private $api_key;

    const EXCHANGE_RATES_API_URL = 'http://api.exchangeratesapi.io/latest?base=EUR&access_key=';

    public function __construct($api_key)
    {
        $this->api_key = $api_key;
    }

    public function getRates($baseCurrency)
    {
        $ch = curl_init(self::EXCHANGE_RATES_API_URL.$this->api_key);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $exchangeData = @json_decode(curl_exec($ch), true);
        curl_close($ch);

        return $exchangeData;
    }
}

class BinlistNet implements BinProvider
{
    const BINLIST_API_URL = 'https://lookup.binlist.net/';

    public function getBinData($bin)
    {
        $binResults = @file_get_contents(self::BINLIST_API_URL.$bin);
        return json_decode($binResults);
    }
}

class CommissionCalculator
{
    private $currencyRateProvider;
    private $binProvider;
    private $euCountries;

    const EU_COMMISSION_RATE = 0.01;
    const NON_EU_COMMISSION_RATE = 0.02;

    public function __construct(CurrencyRateProvider $currencyRateProvider, BinProvider $binProvider, array $euCountries)
    {
        $this->currencyRateProvider = $currencyRateProvider;
        $this->binProvider = $binProvider;
        $this->euCountries = $euCountries;
    }

    public function calculateCommissions($inputFile)
    {
        $commissionData = [];

        $lines = file($inputFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $data = json_decode($line, true);

            if ($data !== null && isset($data['bin'], $data['amount'], $data['currency'])) {
                $commissionData[] = $this->calculateCommission($data['bin'], $data['amount'], $data['currency']);
            }
        }

        return $commissionData;
    }

    public function calculateCommission($bin, $amount, $currency)
    {
        $binData = $this->binProvider->getBinData($bin);
        $isEu = $this->isEu($binData->country->alpha2);

        $exchangeData = $this->currencyRateProvider->getRates('EUR');
        $rate = $exchangeData['rates'][$currency];

        $amntFixed = ($currency == 'EUR' || $rate == 0) ? $amount : $amount / $rate;
        $commissionRate = $isEu ? self::EU_COMMISSION_RATE : self::NON_EU_COMMISSION_RATE;
        $commission = $amntFixed * $commissionRate;

        return ceil($commission * 100) / 100;
    }

    public function isEu($countryCode)
    {
        return in_array($countryCode, $this->euCountries);
    }
}

$api_key = 'f7d51465265d2166aa22097ad68eb7ad';
$euCountries = ['AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PO', 'PT', 'RO', 'SE', 'SI', 'SK'];
$currencyRateProvider = new ExchangeRatesApi($api_key);
$binProvider = new BinlistNet();

$calculator = new CommissionCalculator($currencyRateProvider, $binProvider, $euCountries);

try {
    $commissions = $calculator->calculateCommissions('input.txt');
    foreach ($commissions as $commission) {
        echo "$commission\n";
    }
} catch (Exception $e) {
    echo "Error: ".$e->getMessage()."\n";
}