#!/usr/bin/env bash

if [ -z "$1" ]; then
echo "Kullanım: $0 kullaniciadi"
exit 1
fi

USERNAME="$1"

echo "[+] Kullanıcı: $USERNAME"

HTML=$(curl -s -L -A "Mozilla/5.0" "https://www.instagram.com/$USERNAME/")

PP_URL=$(echo "$HTML" | grep -oP '"profile_pic_url_hd":"\K[^"]+' | sed 's/\u0026/&/g')

# fallback (hd yoksa normal)

if [ -z "$PP_URL" ]; then
PP_URL=$(echo "$HTML" | grep -oP '"profile_pic_url":"\K[^"]+' | sed 's/\u0026/&/g')
fi

if [ -z "$PP_URL" ]; then
echo "[-] Bulunamadı. Instagram engellemiş olabilir."
exit 1
fi

echo "[+] Bulundu!"
curl -L "$PP_URL" -o "${USERNAME}_pp.jpg"

echo "[✓] Kaydedildi: ${USERNAME}_pp.jpg"
