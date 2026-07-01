<?php

namespace Tek2991\Accounting\Utilities;

use Tek2991\Accounting\Models\State;

class GstinValidator
{
    /**
     * Validate a GSTIN and optionally check if its state code matches a provided state.
     */
    public function validate(string $gstin, ?State $state = null): GstinValidationResult
    {
        $errors = [];
        $isValidFormat = true;
        $isValidStateCode = true;

        $gstin = strtoupper(trim($gstin));

        // 1. Basic Format Validation (15 chars, alphanumeric)
        // Format: 2 digits (state code) + 10 chars (PAN) + 1 digit (entity code) + 1 char (Z default) + 1 char (checksum)
        if (!preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/', $gstin)) {
            $isValidFormat = false;
            $errors[] = 'Invalid GSTIN format. Expected format: 22AAAAA0000A1Z5';
        }

        // 2. State Code validation
        if ($isValidFormat && $state && $state->gst_state_code) {
            $gstinStateCode = substr($gstin, 0, 2);
            if ($gstinStateCode !== $state->gst_state_code) {
                $isValidStateCode = false;
                $errors[] = "GSTIN state code ({$gstinStateCode}) does not match the selected state's code ({$state->gst_state_code}).";
            }
        }

        return new GstinValidationResult($isValidFormat, $isValidStateCode, $errors);
    }

    /**
     * Extracts the 2-digit state code from a given GSTIN
     */
    public function extractStateCode(string $gstin): ?string
    {
        $gstin = strtoupper(trim($gstin));
        
        if (strlen($gstin) >= 2) {
            $code = substr($gstin, 0, 2);
            if (is_numeric($code)) {
                return $code;
            }
        }
        
        return null;
    }
}
