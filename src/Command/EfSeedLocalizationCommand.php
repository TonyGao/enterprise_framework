<?php

namespace App\Command;

use App\Entity\Platform\Country;
use App\Entity\Platform\Currency;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Intl\Currencies;

/**
 * 国际化参考数据初始化 / Seed internationalization reference data.
 *
 * 从 symfony/intl 生成币种与国家基础数据（多币种/多国家支持）。
 * Seeds currency and country reference data from symfony/intl
 * (multi-currency / multi-country support).
 */
#[AsCommand(
    name: 'ef:seed-localization',
    description: '初始化币种/国家参考数据（国际化）/ Seed currency & country reference data (i18n)',
)]
class EfSeedLocalizationCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // --- 币种 / Currencies ---
        $currencyRepo = $this->em->getRepository(Currency::class);
        $currencyCount = 0;
        $currencyCodes = Currencies::getCurrencyCodes();
        foreach ($currencyCodes as $code) {
            $exists = $currencyRepo->findOneBy(['code' => $code]);
            if ($exists) {
                continue;
            }
            try {
                $currency = new Currency();
                $currency->setCode($code)
                    ->setSymbol(Currencies::getSymbol($code))
                    ->setDecimals(Currencies::getFractionDigits($code))
                    ->setEnabled(true);
                $this->em->persist($currency);
                $currencyCount++;
            } catch (\Throwable) {
                // 跳过异常币种 / Skip problematic currencies
            }
        }

        // --- 国家 / Countries ---
        $countryRepo = $this->em->getRepository(Country::class);
        $countryCount = 0;
        foreach (Countries::getCountryCodes() as $code) {
            $exists = $countryRepo->findOneBy(['code' => $code]);
            if ($exists) {
                continue;
            }
            try {
                $country = new Country();
                $country->setCode($code)
                    ->setName(Countries::getName($code, 'en'));
                $currency = Currencies::forCountry($code);
                if (is_array($currency) && !empty($currency)) {
                    $country->setCurrencyCode($currency[0]);
                } elseif (is_string($currency) && $currency !== '') {
                    $country->setCurrencyCode($currency);
                }
                // 默认语言：优先映射到系统支持的语言（en），其余国家用英文名标注 /
                // Default locale: prefer supported locales, others fall back to en
                $country->setLocale($this->mapLocale($code));
                $country->setEnabled(true);
                $this->em->persist($country);
                $countryCount++;
            } catch (\Throwable) {
                // 跳过异常国家 / Skip problematic countries
            }
        }

        $this->em->flush();

        $io->success(sprintf(
            '币种/Currencies: +%d 条; 国家/Countries: +%d 条',
            $currencyCount,
            $countryCount
        ));

        return Command::SUCCESS;
    }

    /**
     * 粗略的国家默认语言映射 / Coarse country-to-locale mapping.
     */
    private function mapLocale(string $countryCode): string
    {
        $map = [
            'CN' => 'zh_CN', 'TW' => 'zh_CN', 'HK' => 'zh_CN', 'SG' => 'zh_CN',
            'US' => 'en', 'GB' => 'en', 'AU' => 'en', 'CA' => 'en', 'IE' => 'en',
        ];

        return $map[$countryCode] ?? 'en';
    }
}
