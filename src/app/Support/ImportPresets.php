<?php

namespace App\Support;

use App\Services\CsvImportService;

/**
 * Named column-mapping presets for {@see CsvImportService}. Each
 * preset maps app fields to the CSV headers a given source produces; YNAB used
 * to be a hard-coded parser, it is now just one entry here. Drop in new sources
 * (Mint, Monarch, a specific bank export) by adding a preset.
 *
 * The `columns` shape mirrors the importer's mapping: keys are app fields
 * (account, date, payee, category, memo, inflow, outflow, amount); values are
 * the source's header names (empty string = not present in this source).
 */
class ImportPresets
{
    /**
     * The mappable app fields, in display order. Single source of truth: the
     * preview view derives both its column-mapping selects and the empty-mapping
     * shape from this, and presets key their `columns` off the same names.
     *
     * @var array<string, array{label: string, required: bool}>
     */
    public const FIELDS = [
        'account'  => ['label' => 'Account column', 'required' => false],
        'date'     => ['label' => 'Date', 'required' => true],
        'amount'   => ['label' => 'Amount (signed)', 'required' => false],
        'inflow'   => ['label' => 'Inflow', 'required' => false],
        'outflow'  => ['label' => 'Outflow', 'required' => false],
        'payee'    => ['label' => 'Payee', 'required' => false],
        'category' => ['label' => 'Category', 'required' => false],
        'memo'     => ['label' => 'Memo', 'required' => false],
    ];

    public const DATE_FORMATS = ['m/d/Y', 'Y-m-d', 'd/m/Y', 'm-d-Y', 'd-m-Y', 'd.m.Y'];

    /** Field descriptors as a flat list ({key, label, required}) for the Alpine view. */
    public static function fieldList(): array
    {
        return array_map(
            fn (string $key, array $meta) => ['key' => $key] + $meta,
            array_keys(self::FIELDS),
            array_values(self::FIELDS),
        );
    }

    /** @return array<string, array{label: string, columns: array<string,string>, date_format: string}> */
    public static function all(): array
    {
        return [
            'ynab' => [
                'label'   => 'YNAB — "All Transactions" export',
                'columns' => [
                    'account'  => 'Account',
                    'date'     => 'Date',
                    'payee'    => 'Payee',
                    'category' => 'Category',
                    'memo'     => 'Memo',
                    'outflow'  => 'Outflow',
                    'inflow'   => 'Inflow',
                    'amount'   => '',
                ],
                'date_format' => 'm/d/Y',
            ],
        ];
    }

    /**
     * Best-guess preset whose key columns (date + account) all appear in the
     * given header row, so the preview can pre-fill the mapping.
     *
     * @param  list<string>  $headers
     */
    public static function detect(array $headers): ?string
    {
        foreach (self::all() as $key => $preset) {
            $needed = array_filter([
                $preset['columns']['date'] ?? '',
                $preset['columns']['account'] ?? '',
            ]);

            if ($needed !== [] && array_diff($needed, $headers) === []) {
                return $key;
            }
        }

        return null;
    }
}
