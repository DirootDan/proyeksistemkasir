<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aplikasi Kasir Salon</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* 1. VARIABEL WARNA (Ganti di sini, berubah semua) */
        :root {
            --primary: #ec4899;      /* Pink Utama */
            --primary-dark: #be185d; /* Pink Gelap */
            --bg-body: #f3f4f6;      /* Abu-abu background */
            --white: #ffffff;
            --text-dark: #1f2937;
            --text-gray: #6b7280;
            --sidebar-width: 250px;
        }

        /* 2. RESET UMUM */
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-dark);
            margin: 0; padding: 0;
            display: flex;
            min-height: 100vh;
        }

        /* 3. KOMPONEN UMUM */
        a { text-decoration: none; color: inherit; }
        
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: 0.2s;
            display: inline-block;
        }
        .btn-primary { background: var(--primary); color: var(--white); }
        .btn-primary:hover { background: var(--primary-dark); transform: translateY(-2px); }
        
        .card {
            background: var(--white);
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }

        input, select, textarea {
            width: 100%;
            padding: 10px;
            margin: 8px 0 15px 0;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            box-sizing: border-box; /* Agar padding tidak melebarkan elemen */
            font-family: inherit;
        }
        input:focus { outline: 2px solid var(--primary); border-color: transparent; }

        /* Tampilan Khusus PHP Desktop agar tidak bisa di-select teksnya (seperti aplikasi native) */
        .no-select {
            user-select: none;
            -webkit-user-select: none;
        }
    </style>
</head>
<body>