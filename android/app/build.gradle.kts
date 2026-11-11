plugins {
    alias(libs.plugins.android.application)
}

// ---------------------------------------------------------------------------------------------
//  SERVER ADDRESS — the ONLY place you need to edit before a release build.
//  Debug builds talk to XAMPP on the development PC (10.0.2.2 = the host machine from the
//  Android emulator; use your PC's LAN IP, e.g. http://192.168.1.20/smartq_dev/api/user/, when
//  testing on a real phone over Wi-Fi).
//  Release builds talk to the deployed site (e.g. InfinityFree). Replace YOUR-DOMAIN with the
//  real domain. The URL must end with "/api/user/". https:// is recommended; if you must use
//  plain http:// the build automatically allows cleartext traffic for the release APK.
// ---------------------------------------------------------------------------------------------
// Override from the command line, e.g. testing on a real phone over Wi-Fi:
//   gradlew assembleDebug -PapiUrl=http://<PC-LAN-IP>/smartq_dev/api/user/
val debugApiBaseUrl = (project.findProperty("apiUrl") as String?) ?: "http://10.0.2.2/smartq_dev/api/user/"
val releaseApiBaseUrl = "https://YOUR-DOMAIN/api/user/"

// ---------------------------------------------------------------------------------------------
//  GOOGLE SIGN-IN — paste your OAuth 2.0 *Web application* client ID here (Google Cloud Console
//  -> APIs & Services -> Credentials -> "Web client", looks like 1234-abc.apps.googleusercontent.com).
//  It must be the SAME Web client ID that the server lists in web/config GOOGLE_CLIENT_IDS.
//  (The same Cloud project also needs an *Android* OAuth client with package name
//  com.example.smartquser + the SHA-1 of your signing key, otherwise Google finds no account.)
//  Either replace the "" below with the ID, or pass it on the command line:
//    gradlew assembleDebug -PgoogleClientId=1234-abc.apps.googleusercontent.com
//  While it is empty the "Continue with Google" button is simply hidden.
// ---------------------------------------------------------------------------------------------
val googleWebClientId = (project.findProperty("googleClientId") as String?) ?: ""

android {
    namespace = "com.example.smartquser"
    compileSdk = 36

    defaultConfig {
        applicationId = "com.example.smartquser"
        minSdk = 24
        targetSdk = 36
        versionCode = 3
        versionName = "3.0"

        testInstrumentationRunner = "androidx.test.runner.AndroidJUnitRunner"
        buildConfigField("String", "GOOGLE_WEB_CLIENT_ID", "\"$googleWebClientId\"")
    }

    buildTypes {
        debug {
            buildConfigField("String", "API_BASE_URL", "\"$debugApiBaseUrl\"")
            manifestPlaceholders["networkSecurityConfig"] = "@xml/network_security_config_debug"
        }
        release {
            buildConfigField("String", "API_BASE_URL", "\"$releaseApiBaseUrl\"")
            manifestPlaceholders["networkSecurityConfig"] =
                if (releaseApiBaseUrl.startsWith("http://")) "@xml/network_security_config_cleartext"
                else "@xml/network_security_config_release"
            isMinifyEnabled = false
            proguardFiles(
                getDefaultProguardFile("proguard-android-optimize.txt"),
                "proguard-rules.pro"
            )
        }
    }

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_11
        targetCompatibility = JavaVersion.VERSION_11
    }

    buildFeatures {
        buildConfig = true
    }
}

dependencies {
    implementation(libs.appcompat)
    implementation(libs.material)
    implementation(libs.activity)
    implementation(libs.constraintlayout)
    implementation("androidx.recyclerview:recyclerview:1.4.0")
    implementation("androidx.swiperefreshlayout:swiperefreshlayout:1.1.0")
    implementation("androidx.coordinatorlayout:coordinatorlayout:1.3.0")

    implementation("androidx.viewpager2:viewpager2:1.1.0")

    // "Continue with Google" through AndroidX Credential Manager
    implementation("androidx.credentials:credentials:1.5.0")
    implementation("androidx.credentials:credentials-play-services-auth:1.5.0")
    implementation("com.google.android.libraries.identity.googleid:googleid:1.1.1")

    implementation("com.github.bumptech.glide:glide:4.16.0")

    testImplementation("junit:junit:4.13.2")
    androidTestImplementation("androidx.test.ext:junit:1.2.1")
    androidTestImplementation("androidx.test.espresso:espresso-core:3.6.1")
}
