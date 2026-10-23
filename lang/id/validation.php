<?php

// Only keys used so far (Fase 1-5); other keys fall back to en.
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
        'file' => 'Ukuran :attribute maksimal :max KB.',
    ],
    'file' => ':attribute harus berupa berkas.',
    'uploaded' => 'Gagal mengunggah :attribute.',
    'integer' => ':attribute harus berupa bilangan bulat.',
    'string' => ':attribute harus berupa teks.',
    'regex' => 'Format :attribute tidak valid.',
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
        'berkas' => 'berkas',
        'lampiran' => 'lampiran draf',
        'file' => 'berkas',
        'gambar' => 'gambar slide',
        'berkas_sk' => 'berkas SK',
    ],
];
