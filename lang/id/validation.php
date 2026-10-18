<?php

// Only keys used so far (Fase 1-2); other keys fall back to en.
return [
    'required' => ':attribute wajib diisi.',
    'email' => ':attribute harus berupa alamat email yang valid.',
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'min' => [
        'string' => ':attribute minimal :min karakter.',
        'numeric' => ':attribute minimal :min.',
        'array' => ':attribute minimal :min item.',
    ],
    'max' => [
        'string' => ':attribute maksimal :max karakter.',
        'numeric' => ':attribute maksimal :max.',
    ],
    'integer' => ':attribute harus berupa bilangan bulat.',
    'string' => ':attribute harus berupa teks.',
    'array' => ':attribute tidak valid.',
    'boolean' => ':attribute harus berupa ya atau tidak.',
    'distinct' => ':attribute tidak boleh sama dengan yang lain.',
    'in' => ':attribute yang dipilih tidak valid.',
    'enum' => ':attribute yang dipilih tidak valid.',
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
