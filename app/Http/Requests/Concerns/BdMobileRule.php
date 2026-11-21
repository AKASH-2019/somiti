<?php

namespace App\Http\Requests\Concerns;

final class BdMobileRule
{
    /** Bangladesh mobile: 11 digits starting 013–019. */
    public const REGEX = 'regex:/^01[3-9][0-9]{8}$/';

    public const MESSAGE = 'সঠিক মোবাইল নম্বর দিন (যেমন: ০১৭১১২২৩৩৪৪)।';
}
