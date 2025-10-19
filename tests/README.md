# Petruk Tests

Test suite untuk aplikasi Petruk CLI tool.

## Struktur Test

- **tests/Unit/**: Unit tests untuk menguji komponen individual
  - `HelpersTest.php`: Test untuk fungsi helper `run_proc()`
  - `RequireCommandTest.php`: Test untuk class `RequireCommand`

- **tests/Integration/**: Integration tests untuk menguji workflow lengkap
  - `PetrukIntegrationTest.php`: Test end-to-end untuk berbagai skenario penggunaan

- **tests/TestCase.php**: Base test class dengan helper methods

## Menjalankan Test

### Install Dependencies

```bash
composer install
```

### Menjalankan Semua Test

```bash
vendor/bin/phpunit
```

### Menjalankan Test Spesifik

```bash
# Unit tests saja
vendor/bin/phpunit tests/Unit

# Integration tests saja
vendor/bin/phpunit tests/Integration

# Test class tertentu
vendor/bin/phpunit tests/Unit/HelpersTest.php

# Test method tertentu
vendor/bin/phpunit --filter testRunProcWithSuccessfulCommand
```

### Coverage Report

```bash
vendor/bin/phpunit --coverage-html coverage
```

## Test Coverage

Test ini mencakup:

1. **Helper Functions**
   - Testing fungsi `run_proc()` dengan berbagai kondisi command
   - Testing handling output, error, dan exit code

2. **RequireCommand Class**
   - Testing konfigurasi command (arguments, options)
   - Testing method `createComposerJsonFileIfNotExists()`
   - Testing method `checkIfNamaPaketFolderIsLocalFolder()`
   - Testing execution dengan berbagai parameter

3. **Integration Tests**
   - Testing workflow lengkap dengan local package
   - Testing parsing repository URL (HTTP/HTTPS)
   - Testing format SSH repository
   - Testing berbagai option (--dev, --global, --paket)
   - Testing error handling

## Test Environment

- Test menggunakan temporary directories untuk isolasi
- Mocking external dependencies seperti composer dan git commands
- Test tidak memerlukan koneksi internet atau SSH keys yang valid
- Test dapat dijalankan di berbagai environment (local, CI/CD)

## Catatan

- Integration test untuk SSH dan HTTPS repositories mungkin tidak berhasil sepenuhnya karena membutuhkan akses ke repository yang valid
- Test di-design untuk menguji logic aplikasi tanpa side effects
- Temporary directories dibersihkan otomatis setelah test selesai