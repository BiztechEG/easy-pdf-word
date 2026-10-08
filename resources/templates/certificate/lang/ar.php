<?php

return [
    'title' => [
        'completion' => 'شهادة إتمام',
        'attendance' => 'شهادة حضور',
        'participation' => 'شهادة مشاركة',
        'appreciation' => 'شهادة شكر وتقدير',
    ],
    // Passive wording, so it reads right whether the issuer is a شركة or a معهد.
    'intro' => [
        'completion' => 'تُمنح هذه الشهادة إلى',
        'attendance' => 'تُمنح هذه الشهادة إلى',
        'participation' => 'تُمنح هذه الشهادة إلى',
        'appreciation' => 'تُقدَّم هذه الشهادة مع خالص الشكر والتقدير إلى',
    ],
    'statement' => [
        'completion' => ['male' => 'لإتمامه بنجاح', 'female' => 'لإتمامها بنجاح'],
        'attendance' => ['male' => 'لحضوره', 'female' => 'لحضورها'],
        'participation' => ['male' => 'لمشاركته في', 'female' => 'لمشاركتها في'],
        'appreciation' => ['male' => 'تقديراً لجهوده المتميزة في', 'female' => 'تقديراً لجهودها المتميزة في'],
    ],
    'period' => 'خلال الفترة من :from إلى :to',
    'on' => 'بتاريخ :date',
    'hours' => [
        'one' => 'بعدد ساعة تدريبية واحدة',
        'two' => 'بعدد ساعتين تدريبيتين',
        'few' => 'بعدد :hours ساعات تدريبية',
        'many' => 'بعدد :hours ساعة تدريبية',
    ],
    'grade' => 'بتقدير :grade',
    'date' => 'تاريخ الإصدار',
    'number' => 'رقم الشهادة',
    'verify' => 'امسح الرمز للتحقق من الشهادة',
];
