# Değişiklik Günlüğü

## 2026-03-22

### Instagram Resim Kalitesi İyileştirmesi
- `includes/functions.php` — Instagram linklerinden daha yüksek çözünürlükte resim çekiliyor
  - `s150x150` gibi küçük boyutlar `s1080x1080` olarak değiştiriliyor
  - Query parametreleri temizleniyor

## 2026-03-21

### Link Resim Popup Özelliği
- `dashboard.php` — link resimlerine tıklayınca büyük popup'ta açılıyor
  - Popup'ın dışına tıklayınca kapanıyor
  - Resimler %90 ekran boyutuna kadar büyüyebiliyor

### Kategori Görünürlük Özelliği
- `includes/db.php` — categories tablosuna `visible` kolonu eklendi (auto-migration, varsayılan: 1/görünür)
- `categories.php` — her kategorinin yanına göz ikonu eklendi (tıkla → görünür/gizli toggle)
  - Yeni kategori eklerken "Görünür" checkbox'ı eklendi
- `dashboard.php` — gizli kategorilerin linkleri ana ekranda gösterilmiyor
  - Arama yapınca veya kategori filtresi seçince gizli kategoriler de dahil ediliyor
  - Dropdown'da gizli kategorilerin yanında "(gizli)" etiketi gösteriliyor

### Kategori Yönetim Ekranı
- `categories.php` oluşturuldu — tüm kategorilerin tek sayfada yönetildiği ekran
  - Kategorileri listeleme (her birinin yanında link sayısı gösteriliyor)
  - Inline düzenleme (kalem ikonu → yerinde isim değiştirme → kaydet/iptal)
  - Kategori silme (içinde link varsa engelleniyor, uyarı veriyor)
  - Yeni kategori ekleme (sayfanın altındaki input ile)
  - Sadece admin erişebilir
- `dashboard.php` güncellendi — "Kategori" butonu → "Kategoriler" olarak `categories.php`'ye yönlendirildi

| Dosya | Durum |
|---|---|
| `includes/db.php` | Güncellendi (visible kolonu auto-migration) |
| `categories.php` | Güncellendi (görünürlük toggle + checkbox) |
| `dashboard.php` | Güncellendi (gizli kategori filtreleme) |
| `CHANGELOG.md` | Güncellendi |

### Önceki: Kategori Yönetim Ekranı (aynı gün)
