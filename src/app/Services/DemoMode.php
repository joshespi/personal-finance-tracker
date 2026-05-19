<?php

namespace App\Services;

use Illuminate\Support\Str;

class DemoMode
{
    private const NAMES = [
        'Alpha', 'Beacon', 'Cedar', 'Delta', 'Ember',
        'Falcon', 'Granite', 'Harbor', 'Indigo', 'Jasper',
        'Keystone', 'Linden', 'Maple', 'Nordic', 'Onyx', 'Prism',
    ];

    private const TICKERS = [
        'ALPH', 'BCNR', 'CEDR', 'DELT', 'EMBR',
        'FLCN', 'GRNT', 'HRBR', 'INDG', 'JSPR',
        'KYST', 'LNDN', 'MAPL', 'NRDC', 'ONYX', 'PRSM',
    ];

    private ?bool   $cachedActive  = null;
    private ?float  $cachedFactor  = null;
    private ?string $cachedSalt    = null;

    public function isActive(): bool
    {
        return $this->cachedActive ??= (bool) session('demo_mode', (bool) config('app.demo_mode', false));
    }

    public function toggle(): void
    {
        $this->cachedActive = ! $this->isActive();
        session(['demo_mode' => $this->cachedActive]);
    }

    public function amt(float|int $value, int $decimals = 2): string
    {
        if ($this->isActive()) return '••••';
        return number_format($value, $decimals);
    }

    public function scaleScalar(float|int $value): float|int
    {
        return $this->isActive() ? $value * $this->factor() : $value;
    }

    public function n(string $seed): string
    {
        if (! $this->isActive()) return $seed;
        return self::NAMES[abs(crc32($seed . $this->salt())) % count(self::NAMES)];
    }

    public function ticker(string $seed): string
    {
        if (! $this->isActive()) return $seed;
        return self::TICKERS[abs(crc32($seed . $this->salt())) % count(self::TICKERS)];
    }

    public function scaleAmounts(mixed $data): mixed
    {
        if (! $this->isActive()) return $data;
        return $this->walk($data);
    }

    public function scaleValues(array $values): array
    {
        if (! $this->isActive()) return $values;
        return array_map(fn ($v) => is_numeric($v) ? $v * $this->factor() : $v, $values);
    }

    public function scrambleHoldings(array $rows): array
    {
        if (! $this->isActive()) return $rows;
        $f = $this->factor();
        return array_map(function ($h) use ($f) {
            $h['symbol']          = $this->ticker($h['symbol']);
            $h['total_cost']      = ($h['total_cost'] ?? 0) * $f;
            $h['current_price']   = $h['current_price'] !== null ? $h['current_price'] * $f : null;
            $h['current_value']   = $h['current_value'] !== null ? $h['current_value'] * $f : null;
            $h['unrealized_gain'] = $h['unrealized_gain'] !== null ? $h['unrealized_gain'] * $f : null;
            $h['sort_value']      = ($h['sort_value'] ?? 0) * $f;
            $h['portfolios']      = array_map(fn ($p) => array_merge($p, [
                'name'  => $this->n($p['name']),
                'value' => ($p['value'] ?? 0) * $f,
            ]), $h['portfolios'] ?? []);
            return $h;
        }, $rows);
    }

    public function factor(): float
    {
        if ($this->cachedFactor === null) {
            if (! session()->has('demo_factor')) {
                session(['demo_factor' => mt_rand(45, 195) / 100]);
            }
            $this->cachedFactor = (float) session('demo_factor');
        }
        return $this->cachedFactor;
    }

    private function salt(): string
    {
        if ($this->cachedSalt === null) {
            if (! session()->has('demo_salt')) {
                session(['demo_salt' => Str::random(8)]);
            }
            $this->cachedSalt = session('demo_salt');
        }
        return $this->cachedSalt;
    }

    private function walk(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($v) => $this->walk($v), $value);
        }
        if (is_float($value) || (is_int($value) && $value > 0)) {
            return $value * $this->factor();
        }
        return $value;
    }
}
