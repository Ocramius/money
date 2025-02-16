<?php

declare(strict_types=1);

namespace Money\Parser;

use Money\Currencies;
use Money\Currency;
use Money\Exception\ParserException;
use Money\Money;
use Money\MoneyParser;
use Money\Number;
use NumberFormatter;

use function assert;
use function ltrim;
use function str_pad;
use function str_replace;
use function strlen;
use function strpos;
use function substr;

/**
 * Parses a string into a Money object using intl extension.
 */
final class IntlMoneyParser implements MoneyParser
{
    private NumberFormatter $formatter;

    private Currencies $currencies;

    public function __construct(NumberFormatter $formatter, Currencies $currencies)
    {
        $this->formatter  = $formatter;
        $this->currencies = $currencies;
    }

    public function parse(string $money, Currency|null $forceCurrency = null): Money
    {
        $currency = null;
        $decimal  = $this->formatter->parseCurrency($money, $currency);

        if ($decimal === false) {
            throw new ParserException('Cannot parse ' . $money . ' to Money. ' . $this->formatter->getErrorMessage());
        }

        if ($forceCurrency === null) {
            assert(! empty($currency));

            $forceCurrency = new Currency($currency);
        }

        $decimal         = (string) $decimal;
        $subunit         = $this->currencies->subunitFor($forceCurrency);
        $decimalPosition = strpos($decimal, '.');

        if ($decimalPosition !== false) {
            $decimalLength  = strlen($decimal);
            $fractionDigits = $decimalLength - $decimalPosition - 1;
            $decimal        = str_replace('.', '', $decimal);
            $decimal        = Number::roundMoneyValue($decimal, $subunit, $fractionDigits);

            if ($fractionDigits > $subunit) {
                $decimal = substr($decimal, 0, $decimalPosition + $subunit);
            } elseif ($fractionDigits < $subunit) {
                $decimal .= str_pad('', $subunit - $fractionDigits, '0');
            }
        } else {
            $decimal .= str_pad('', $subunit, '0');
        }

        if ($decimal[0] === '-') {
            $decimal = '-' . ltrim(substr($decimal, 1), '0');
        } else {
            $decimal = ltrim($decimal, '0');
        }

        if ($decimal === '') {
            $decimal = '0';
        }

        /** @psalm-var numeric-string $decimal */
        return new Money($decimal, $forceCurrency);
    }
}
