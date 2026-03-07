// DESCRIPTION: Reusable date range picker component using WordPress DatePicker
// with popover-wrapped calendars for selecting a from/to date range.

import { useState, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { DatePicker } from '@wordpress/components';

function DatePopover({ label, value, onChange }) {
  const [open, setOpen] = useState(false);
  const ref = useRef(null);

  const formatted = value
    ? new Date(value).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
      })
    : __('Select date', 'content-forge');

  return (
    <div className="cforge-relative" ref={ref}>
      <label className="cforge-block cforge-mb-1 cforge-text-sm cforge-font-medium cforge-text-gray-700">
        {label}
      </label>
      <button
        type="button"
        className="cforge-input cforge-text-left cforge-flex cforge-items-center cforge-gap-2"
        onClick={() => setOpen(!open)}
      >
        <svg
          className="cforge-w-4 cforge-h-4 cforge-text-gray-400 cforge-flex-shrink-0"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
          />
        </svg>
        <span className={value ? 'cforge-text-gray-900' : 'cforge-text-gray-400'}>
          {formatted}
        </span>
      </button>
      {open && (
        <>
          <div
            className="cforge-fixed cforge-inset-0 cforge-z-40"
            onClick={() => setOpen(false)}
          />
          <div className="cforge-absolute cforge-z-50 cforge-mt-1 cforge-bg-white cforge-border cforge-border-gray-200 cforge-rounded-lg cforge-shadow-lg">
            <DatePicker
              currentDate={value || new Date().toISOString()}
              onChange={(date) => {
                onChange(date);
                setOpen(false);
              }}
            />
          </div>
        </>
      )}
    </div>
  );
}

export default function DateRangePicker({ dateFrom, dateTo, onDateFromChange, onDateToChange }) {
  return (
    <div className="cforge-grid cforge-grid-cols-2 cforge-gap-4">
      <DatePopover
        label={__('From', 'content-forge')}
        value={dateFrom}
        onChange={onDateFromChange}
      />
      <DatePopover
        label={__('To', 'content-forge')}
        value={dateTo}
        onChange={onDateToChange}
      />
    </div>
  );
}
