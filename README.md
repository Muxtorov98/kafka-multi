
### ⚡ MUXTOROV98 / KAFKA MULTI
- Universal Kafka for PHP (Yii2 • Laravel • Symfony)

## 🚀 Installation

## Kafka + Zookeeper + Kafka UI — Docker Compose Setup
https://github.com/Muxtorov98/docker-compose-kafka.yml

## 🧩 PHP uchun Kafka Extension (rdkafka) o‘rnatish

Kafka bilan ishlash uchun php-rdkafka extension talab etiladi.Bu extension librdkafka kutubxonasiga asoslanadi va Kafka producer / consumer funksiyalarini PHP orqali amalga oshirishga imkon beradi.

## 🐳 Docker muhiti uchun

```dockerfile
# --- Kafka extension (rdkafka) ---
RUN pecl install rdkafka \
    && docker-php-ext-enable rdkafka \
    && rm -rf /tmp/pear

# --- PCNTL extension (background process control) ---
RUN docker-php-ext-install pcntl
```

## Izoh:

- rdkafka — Kafka bilan ishlash uchun asosiy extension

- pcntl — workerlarni parallel ishlashini (multi-process) ta’minlaydi

## 🖥️ Ubuntu’da o‘rnatish

```bash
sudo apt update
sudo apt install -y php-dev librdkafka-dev librssl-dev build-essential

sudo pecl install rdkafka
echo "extension=rdkafka.so" | sudo tee /etc/php/$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")/mods-available/rdkafka.ini
sudo phpenmod rdkafka

# PCNTL moduli
sudo docker-php-ext-install pcntl  # agar dockerda bo‘lmasa
```

## Keyin PHP versiyasini tekshiring:

```bash
php -m | grep rdkafka
```
- Agar rdkafka va pcntl ko‘rinsa — hammasi tayyor ✅


---
## 🧱 Framework hujjatlari

🧱 **Symfony**  
➡️ [SYMFONY-README.md](https://github.com/Muxtorov98/kafka-multi/blob/main/SYMFONY-README.md)

🐘 **Laravel**  
➡️ [LARAVEL-README.md](https://github.com/Muxtorov98/kafka-multi/blob/main/LARAVEL-README.md)

🐉 **Yii2**  
➡️ [YII2-README.md](https://github.com/Muxtorov98/kafka-multi/blob/main/YII-README.md)