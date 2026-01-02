# Link Management System (Link Yöneticisi)

Bu proje, PHP ve SQLite kullanılarak geliştirilmiş modern, hızlı ve kullanıcı dostu bir Link Yönetim Sistemidir. Kişisel veya ekip içi link arşivleme ihtiyaçları için tasarlanmıştır.

## 🌟 Özellikler

*   **Modern Arayüz:** Göz yormayan ve şık "Glassmorphism" (Buzlu Cam) tasarımı.
*   **Kolay Kurulum:** Veritabanı kurulumu gerektirmez (SQLite). Dosyaları kopyalayıp çalıştırabilirsiniz.
*   **Otomatik Başlık Getirme:** Link eklerken sadece URL girdiğinizde, sitenin başlığını otomatik olarak çeker.
*   **Kategori Yönetimi:** Linklerinizi kategoriler altında düzenleyebilir ve filtreleyebilirsiniz.
*   **Yetkilendirme Sistemi:**
    *   **Admin:** Tüm linkleri ve kategorileri görür, kullanıcıları yönetir.
    *   **Kullanıcı:** Sadece adminin izin verdiği kategorileri görebilir ve yönetebilir.
*   **Kullanıcı Yönetimi:** Sınırsız kullanıcı ekleme ve yetkilendirme.

## 🚀 Gereksinimler

*   PHP 7.4 veya üzeri
*   PHP Eklentileri: `php-sqlite3`, `php-curl`, `php-mbstring`, `php-xml`

## 🛠 Kurulum

1.  Bu projeyi sunucunuza indirin veya kopyalayın.
2.  Terminali açın ve proje dizinine gidin.
3.  Eğer yerel bilgisayarınızda deneyecekseniz şu komutu çalıştırın:
    ```bash
    php -S localhost:8080
    ```
4.  Tarayıcınızdan `http://localhost:8080` adresine gidin.

> **Not:** `database.sqlite` dosyası proje kök dizininde otomatik olarak oluşturulacaktır. Klasörün yazma izninin olduğundan emin olun.

## 🔐 Varsayılan Giriş

Sistem ilk kez çalıştırıldığında aşağıdaki yönetici hesabı otomatik oluşturulur:

*   **Kullanıcı Adı:** `admin`
*   **Şifre:** `admin`

> Güvenliğiniz için giriş yaptıktan sonra şifrenizi değiştirmeniz veya yeni bir yönetici oluşturup varsayılan hesabı silmeniz önerilir.

## 📸 Ekran Görüntüleri

Tasarım, glassmorphism efektleri ve responsive yapı ile her cihazda düzgün görünür.

---
Basit, hızlı ve etkili bir link arşivi için geliştirilmiştir.
