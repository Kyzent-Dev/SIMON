<?php

// Only keys used in Fase 1; other keys fall back to en.
return [
    'required' => ':attribute wajib diisi.',
    'email' => ':attribute harus berupa alamat email yang valid.',
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'min' => [
        'string' => ':attribute minimal :min karakter.',
    ],
    'unique' => ':attribute sudah digunakan.',
    'password' => [
        'letters' => ':attribute harus mengandung setidaknya satu huruf.',
        'mixed' => ':attribute harus mengandung huruf besar dan huruf kecil.',
        'numbers' => ':attribute harus mengandung setidaknya satu angka.',
        'symbols' => ':attribute harus mengandung setidaknya satu simbol.',
        'uncompromised' => ':attribute pernah bocor dalam kebocoran data. Pilih :attribute yang lain.',
    ],
    'attributes' => [
        'username' => 'nama pengguna',
        'password' => 'kata sandi',
        'email' => 'email',
    ],
];
