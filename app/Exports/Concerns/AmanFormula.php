<?php

namespace App\Exports\Concerns;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

/**
 * Value binder for exports (use in a class extending DefaultValueBinder, with WithCustomValueBinder):
 * strings that Excel could read as a formula (`= + - @`, tab, CR) are stored as text.
 */
trait AmanFormula
{
    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_string($value) && preg_match('/^[=+\-@\t\r]/', $value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }
}
