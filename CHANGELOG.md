# Değişiklik Günlüğü

## 2026-03-21

### Kategori Yönetim Ekranı
- `categories.php` oluşturuldu — tüm kategorilerin tek sayfada yönetildiği ekran
  - Kategorileri listeleme (her birinin yanında link sayısı gösteriliyor)
  - Inline düzenleme (kalem ikonu → yerinde isim değiştirme → kaydet/iptal)
  - Kategori silme (içinde link varsa engelleniyor, uyarı veriyor)
  - Yeni kategori ekleme (sayfanın altındaki input ile)
  - Sadece admin erişebilir
- `dashboard.php` güncellendi — "Kategori" butonu → "Kategoriler" olarak `categories.php`'ye yönlendirildi

### Etkilenen Dosyalar
| Dosya | Durum |
|---|---|
| `categories.php` | Yeni |
| `dashboard.php` | Güncellendi (kategori butonu linki) |
