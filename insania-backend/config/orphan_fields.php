<?php

/**
 * Every piece of information kept about an orphan. ONE list, used by: the staff Add / Edit form, the profile page,
 * the public application form, and the step where staff approve an application into a record.
 * (The labels and layout live in the app: prototype/app/assets/orphan-schema.js. Keep the keys the same.)
 *
 *   'staff' => true   only the foundation fills this in: it is never asked on the public form.
 *   guardian_* and bank_* belong to the guardian record and the bank account; every other key is a column of `children`.
 */
return [
    'fields' => [
        // Information
        'name_en' => ['string', 'max:150'],
        'name_ar' => ['string', 'max:150'],
        'gender' => ['in:m,f'],
        'birth_date' => ['date', 'before:today'],
        'nationality' => ['string', 'max:100'],
        'channel_label' => ['string', 'max:100', 'staff' => true],
        'source_label' => ['string', 'max:150', 'staff' => true],
        'sponsor_name' => ['string', 'max:150', 'staff' => true],
        'sponsor_file_no' => ['string', 'max:60', 'staff' => true],
        // Health and study
        'health_status' => ['in:good,needs_followup'],
        'health_details' => ['string', 'max:1000'],
        'study_level' => ['in:none,kindergarten,primary,intermediate,secondary,university,vocational'],
        'grade' => ['string', 'max:40'],
        'madrasa_grade' => ['string', 'max:60'],
        'reason_not_studying' => ['string', 'max:500'],
        'quran_memorization' => ['string', 'max:150'],
        'performance' => ['in:weak,average,good,very_good,excellent', 'staff' => true],
        'islamic_commitment' => ['in:weak,average,good,very_good,excellent', 'staff' => true],
        // Where the child lives
        'birth_country' => ['string', 'max:100'],
        'residence_country' => ['string', 'max:100'],
        'city' => ['string', 'max:100'],
        'address_ar' => ['string', 'max:300'],
        'address_en' => ['string', 'max:300'],
        // Mother and family
        'mother_name_ar' => ['string', 'max:150'],
        'mother_name' => ['string', 'max:150'],          // the English name (the column existed before)
        'mother_marital_status' => ['string', 'max:40'],
        'mother_works' => ['in:yes,no'],
        'mother_salary' => ['numeric', 'min:0', 'max:10000000'],
        'father_death_date' => ['date'],
        'brothers' => ['integer', 'min:0', 'max:30'],
        'sisters' => ['integer', 'min:0', 'max:30'],
        'aided_siblings' => ['integer', 'min:0', 'max:30'],
        'other_aid' => ['string', 'max:500'],
        // Guardian and responsible person
        'guardian_name' => ['string', 'max:150'],
        'guardian_relation' => ['string', 'max:60'],
        'guardian_phone' => ['string', 'max:40'],
        'responsible_person' => ['string', 'max:150', 'staff' => true],
        'responsible_name_ar' => ['string', 'max:150'],
        'responsible_name_en' => ['string', 'max:150'],
        'responsible_relation' => ['string', 'max:60'],
        'responsible_since' => ['date'],
        // Bank account (only the last four digits are ever kept)
        'bank_holder' => ['string', 'max:150'],
        'bank_name' => ['string', 'max:100'],
        'bank_last4' => ['regex:/^\d{4}$/'],
        // Foundation notes
        'notable_changes' => ['string', 'max:1500', 'staff' => true],
        'organization_comment' => ['string', 'max:1500', 'staff' => true],
        'message_to_sponsor' => ['string', 'max:1500', 'staff' => true],
        'delay_reason' => ['string', 'max:500', 'staff' => true],
        'expected_send_date' => ['date', 'staff' => true],
    ],

    'photos' => ['max_files' => 2, 'max_kb' => 4096, 'max_per_child' => 8],
    // Papers sent with an application (birth certificate, death certificate, school record...): photos of the pages.
    'documents' => ['max_files' => 5, 'max_kb' => 8192],
    // Applications from the public form: how many a visitor may send per hour, and in total per day.
    'apply' => ['per_hour' => 6, 'per_day' => 200],
];
