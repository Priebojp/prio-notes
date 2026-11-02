# Prio Notes – Android app

NativePHP Mobile (Laravel + Livewire running on the phone) that talks to the
server's API (`../server/routes/api.php`) with a Sanctum token.

Screens: **Teraz** (urgent list, complete, timer, quick add with the
"what moves down" sheet) and **Porada** (record with the microphone plugin,
upload for transcription). The token is kept in the OS keystore when
`SecureStorage` is available, otherwise encrypted in the app's private database.

## Build machine setup (Linux)

- Android Studio + SDK in `~/Android/Sdk` (Platform-Tools, Build-Tools, an emulator image)
- **JDK 17** for Gradle (`sudo apt install openjdk-17-jdk`); Android Studio's bundled JDK 25 is too new for the Gradle version NativePHP ships
- `.env`:
  ```
  NATIVEPHP_APP_ID=sk.prieboj.prionotes
  PRIO_SERVER_URL=https://note.prieboj.sk   # fixed per build; emulator against a local server: http://10.0.2.2:8000
  ```

```bash
export ANDROID_HOME=$HOME/Android/Sdk JAVA_HOME=/usr/lib/jvm/java-17-openjdk-amd64
export PATH=$JAVA_HOME/bin:$ANDROID_HOME/platform-tools:$ANDROID_HOME/emulator:$PATH
composer install && npm install && npm run build
php artisan native:install android      # once; nativephp/ is generated, not committed
php artisan native:run android          # build + install on a connected phone / emulator
```

Phone over USB: enable Developer options → USB debugging, check `adb devices`.

Release APK (sideload, no Play Store needed):

```bash
php artisan native:credentials android  # creates a keystore, writes ANDROID_KEY* to .env
php artisan native:package android      # → nativephp/android/app/build/outputs/apk/release/app-release.apk
```

Bump `NATIVEPHP_APP_VERSION_CODE` before each build you install over an older one.

Tests: `./vendor/bin/pest` (HTTP to the server is faked).
