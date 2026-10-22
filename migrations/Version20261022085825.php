<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Online payments, promo codes, gift certificates, add-ons, waitlist, messenger notifications,
 * FAQ and blog, audit log and two-factor authentication.
 */
final class Version20261022085825 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Payments, promo codes, gift certificates, extras, waitlist, messengers, FAQ, blog, audit log, 2FA';
    }

    /** @var list<array{question: array<string, string>, answer: array<string, string>}> */
    private const FAQ = [
        [
            'question' => [
                'ru' => 'Нужен ли опыт для участия в сплаве?',
                'be' => 'Ці патрэбны вопыт для ўдзелу ў сплаве?',
                'en' => 'Do I need any experience?',
            ],
            'answer' => [
                'ru' => 'Нет. Инструкторы проводят подробный инструктаж перед стартом и учат всему необходимому. Большинство маршрутов подходят для новичков — главное желание и соблюдение инструкций.',
                'be' => 'Не. Інструктары праводзяць падрабязны інструктаж перад стартам і вучаць усяму неабходнаму. Большасць маршрутаў падыходзяць для пачаткоўцаў — галоўнае жаданне і выкананне інструкцый.',
                'en' => 'No. Our instructors give a thorough briefing before the start and teach you everything you need. Most routes are suitable for beginners — all you need is enthusiasm and to follow the instructions.',
            ],
        ],
        [
            'question' => [
                'ru' => 'Что входит в стоимость сплава?',
                'be' => 'Што ўваходзіць у кошт сплаву?',
                'en' => 'What does the price include?',
            ],
            'answer' => [
                'ru' => 'В базовую стоимость входит аренда байдарки, вёсел, спасательных жилетов и гермомешков. Питание, палатки, трансфер, услуги инструктора и снаряжение для ночёвки зависят от выбранного пакета — уточняйте при бронировании.',
                'be' => 'У базавы кошт уваходзіць арэнда байдаркі, вёслаў, ратавальных камізэлек і гермамяшкоў. Харчаванне, намёты, трансфер, паслугі інструктара і рыштунак для начоўкі залежаць ад абранага пакета — удакладняйце пры браніраванні.',
                'en' => 'The base price includes the hire of a kayak, paddles, life jackets and dry bags. Meals, tents, transfers, an instructor and camping kit depend on the package you choose — please check when booking.',
            ],
        ],
        [
            'question' => [
                'ru' => 'Можно ли на сплав с детьми?',
                'be' => 'Ці можна на сплаў з дзецьмі?',
                'en' => 'Can I bring children?',
            ],
            'answer' => [
                'ru' => 'Да, дети участвуют в сопровождении взрослых. Мы выдаём спасательные жилеты подходящего размера и подскажем маршруты, которые лучше всего подходят для семейного отдыха.',
                'be' => 'Так, дзеці ўдзельнічаюць у суправаджэнні дарослых. Мы выдаём ратавальныя камізэлькі адпаведнага памеру і падкажам маршруты, якія лепш за ўсё падыходзяць для сямейнага адпачынку.',
                'en' => 'Yes, children can take part when accompanied by adults. We provide life jackets in the right sizes and can suggest the routes best suited to a family day out.',
            ],
        ],
        [
            'question' => [
                'ru' => 'Что делать, если пойдёт дождь?',
                'be' => 'Што рабіць, калі пойдзе дождж?',
                'en' => 'What if it rains?',
            ],
            'answer' => [
                'ru' => 'Лёгкий дождь не помеха и даже добавляет приключению шарма — возьмите дождевик. При сильном дожде, грозе или штормовом предупреждении перенесём сплав на другую дату.',
                'be' => 'Лёгкі дождж не перашкода і нават дадае прыгодзе шарму — вазьміце дажджавік. Пры моцным дажджы, навальніцы або штармавым папярэджанні перанясём сплаў на іншую дату.',
                'en' => 'Light rain is no problem and even adds some charm to the adventure — just bring a raincoat. In heavy rain, a thunderstorm or a storm warning, we’ll move the trip to another date.',
            ],
        ],
        [
            'question' => [
                'ru' => 'Что взять с собой?',
                'be' => 'Што ўзяць з сабой?',
                'en' => 'What should I bring?',
            ],
            'answer' => [
                'ru' => "Рекомендуем взять:\n\n- Сменную одежду и обувь\n- Головной убор и солнцезащитные очки\n- Крем от солнца и средство от насекомых\n- Питьевую воду и перекус\n- Телефон в водонепроницаемом чехле",
                'be' => "Рэкамендуем узяць:\n\n- Запасную вопратку і абутак\n- Галаўны ўбор і сонцаахоўныя акуляры\n- Крэм ад сонца і сродак ад насякомых\n- Пітную ваду і перакус\n- Тэлефон у воданепранікальным чахле",
                'en' => "We recommend bringing:\n\n- A change of clothes and shoes\n- A hat and sunglasses\n- Sunscreen and insect repellent\n- Drinking water and snacks\n- Your phone in a waterproof case",
            ],
        ],
        [
            'question' => [
                'ru' => 'Организуете корпоративные сплавы?',
                'be' => 'Арганізуеце карпаратыўныя сплавы?',
                'en' => 'Do you organise corporate trips?',
            ],
            'answer' => [
                'ru' => 'Да, для групп любого размера. Составим программу под ваши пожелания: питание, развлечения, командные игры и тимбилдинг на воде. Свяжитесь с нами, чтобы обсудить детали.',
                'be' => 'Так, для груп любой колькасці. Складзём праграму пад вашы пажаданні: харчаванне, забавы, камандныя гульні і цімбілдынг на вадзе. Звяжыцеся з намі, каб абмеркаваць дэталі.',
                'en' => 'Yes, for groups of any size. We’ll put together a programme to suit you: meals, entertainment, team games and team building on the water. Get in touch to discuss the details.',
            ],
        ],
        [
            'question' => [
                'ru' => 'Насколько это безопасно?',
                'be' => 'Наколькі гэта бяспечна?',
                'en' => 'How safe is it?',
            ],
            'answer' => [
                'ru' => 'Безопасность — наш приоритет. Перед каждым сплавом проводится инструктаж, выдаются спасательные жилеты, инструкторы знают маршрут. Мы выбираем спокойные реки без сложных порогов.',
                'be' => 'Бяспека — наш прыярытэт. Перад кожным сплавам праводзіцца інструктаж, выдаюцца ратавальныя камізэлькі, інструктары ведаюць маршрут. Мы выбіраем спакойныя рэкі без складаных парогаў.',
                'en' => 'Safety is our priority. Every trip starts with a briefing, everyone gets a life jacket, and our instructors know the route. We choose calm rivers without difficult rapids.',
            ],
        ],
    ];

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE audit_log (id BIGINT GENERATED BY DEFAULT AS IDENTITY NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id INT DEFAULT NULL, user_email VARCHAR(180) DEFAULT NULL, action VARCHAR(40) NOT NULL, entity_type VARCHAR(60) DEFAULT NULL, entity_id VARCHAR(40) DEFAULT NULL, label VARCHAR(255) DEFAULT NULL, changes JSON DEFAULT NULL, ip VARCHAR(45) DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_audit_log_created_at ON audit_log (created_at)');
        $this->addSql('CREATE INDEX idx_audit_log_entity ON audit_log (entity_type, entity_id)');
        $this->addSql('CREATE TABLE extras (id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL, name JSON NOT NULL, description JSON NOT NULL, price INT NOT NULL, unit VARCHAR(10) NOT NULL, per_day BOOLEAN DEFAULT false NOT NULL, max_quantity SMALLINT DEFAULT 10 NOT NULL, icon VARCHAR(40) DEFAULT NULL, position INT DEFAULT 0 NOT NULL, is_active BOOLEAN DEFAULT true NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE faq_items (id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL, question JSON NOT NULL, answer JSON NOT NULL, position INT DEFAULT 0 NOT NULL, show_on_home BOOLEAN DEFAULT false NOT NULL, is_active BOOLEAN DEFAULT true NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE gift_certificates (id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL, code VARCHAR(20) NOT NULL, token VARCHAR(32) NOT NULL, amount INT NOT NULL, balance INT NOT NULL, status VARCHAR(12) NOT NULL, buyer_name VARCHAR(100) NOT NULL, buyer_email VARCHAR(180) NOT NULL, recipient_name VARCHAR(100) DEFAULT NULL, message TEXT DEFAULT NULL, locale VARCHAR(5) DEFAULT \'ru\' NOT NULL, expires_at DATE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, activated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_35AC2B9A77153098 ON gift_certificates (code)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_35AC2B9A5F37A13B ON gift_certificates (token)');
        $this->addSql('CREATE INDEX idx_gift_certificates_status ON gift_certificates (status)');
        $this->addSql('CREATE TABLE messenger_subscriptions (id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL, channel VARCHAR(10) NOT NULL, external_id VARCHAR(64) NOT NULL, display_name VARCHAR(100) DEFAULT NULL, is_active BOOLEAN DEFAULT true NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id INT DEFAULT NULL, order_id INT DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_messenger_subscriptions_external ON messenger_subscriptions (channel, external_id)');
        $this->addSql('CREATE INDEX IDX_BB30FFC3A76ED395 ON messenger_subscriptions (user_id)');
        $this->addSql('CREATE INDEX IDX_BB30FFC38D9F6D38 ON messenger_subscriptions (order_id)');
        $this->addSql('CREATE TABLE order_extras (id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL, name JSON NOT NULL, unit VARCHAR(10) NOT NULL, quantity SMALLINT NOT NULL, unit_price INT NOT NULL, total INT NOT NULL, order_id INT NOT NULL, extra_id INT DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_E51475048D9F6D38 ON order_extras (order_id)');
        $this->addSql('CREATE INDEX IDX_E51475042B959FC6 ON order_extras (extra_id)');
        $this->addSql('CREATE TABLE payments (id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL, public_id VARCHAR(32) NOT NULL, provider VARCHAR(20) NOT NULL, amount INT NOT NULL, currency VARCHAR(3) DEFAULT \'BYN\' NOT NULL, status VARCHAR(12) NOT NULL, external_id VARCHAR(128) DEFAULT NULL, transaction_id VARCHAR(128) DEFAULT NULL, redirect_url TEXT DEFAULT NULL, error VARCHAR(255) DEFAULT NULL, refunded_amount INT DEFAULT 0 NOT NULL, test BOOLEAN DEFAULT false NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, paid_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, order_id INT DEFAULT NULL, gift_certificate_id INT DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_65D29B32B5B48B91 ON payments (public_id)');
        $this->addSql('CREATE INDEX idx_payments_status ON payments (status)');
        $this->addSql('CREATE INDEX IDX_65D29B328D9F6D38 ON payments (order_id)');
        $this->addSql('CREATE INDEX IDX_65D29B32A97C76A8 ON payments (gift_certificate_id)');
        $this->addSql('CREATE TABLE posts (id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL, slug VARCHAR(120) NOT NULL, title JSON NOT NULL, excerpt JSON NOT NULL, body JSON NOT NULL, cover_image VARCHAR(255) DEFAULT NULL, author VARCHAR(100) DEFAULT NULL, is_published BOOLEAN DEFAULT false NOT NULL, published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, tour_id INT DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_885DBAFA989D9B62 ON posts (slug)');
        $this->addSql('CREATE INDEX idx_posts_published ON posts (is_published, published_at)');
        $this->addSql('CREATE INDEX IDX_885DBAFA15ED8D43 ON posts (tour_id)');
        $this->addSql('CREATE TABLE promo_codes (id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL, code VARCHAR(32) NOT NULL, type VARCHAR(10) NOT NULL, value INT NOT NULL, valid_from DATE DEFAULT NULL, valid_until DATE DEFAULT NULL, max_uses INT DEFAULT NULL, used_count INT DEFAULT 0 NOT NULL, is_active BOOLEAN DEFAULT true NOT NULL, note VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C84FDDB77153098 ON promo_codes (code)');
        $this->addSql('CREATE TABLE waitlist_entries (id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL, token VARCHAR(32) NOT NULL, start_date DATE NOT NULL, duration_days SMALLINT NOT NULL, people SMALLINT NOT NULL, name VARCHAR(100) NOT NULL, email VARCHAR(180) NOT NULL, phone VARCHAR(20) DEFAULT NULL, locale VARCHAR(5) DEFAULT \'ru\' NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, notified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, tour_id INT DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E74550EE5F37A13B ON waitlist_entries (token)');
        $this->addSql('CREATE INDEX idx_waitlist_start_date ON waitlist_entries (start_date)');
        $this->addSql('CREATE INDEX IDX_E74550EE15ED8D43 ON waitlist_entries (tour_id)');
        $this->addSql('ALTER TABLE messenger_subscriptions ADD CONSTRAINT FK_BB30FFC3A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE messenger_subscriptions ADD CONSTRAINT FK_BB30FFC38D9F6D38 FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE order_extras ADD CONSTRAINT FK_E51475048D9F6D38 FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE order_extras ADD CONSTRAINT FK_E51475042B959FC6 FOREIGN KEY (extra_id) REFERENCES extras (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE payments ADD CONSTRAINT FK_65D29B328D9F6D38 FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE payments ADD CONSTRAINT FK_65D29B32A97C76A8 FOREIGN KEY (gift_certificate_id) REFERENCES gift_certificates (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE posts ADD CONSTRAINT FK_885DBAFA15ED8D43 FOREIGN KEY (tour_id) REFERENCES tours (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE waitlist_entries ADD CONSTRAINT FK_E74550EE15ED8D43 FOREIGN KEY (tour_id) REFERENCES tours (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE orders ADD base_price INT DEFAULT NULL');
        $this->addSql('ALTER TABLE orders ADD extras_price INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE orders ADD discount INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE orders ADD gift_amount INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE orders ADD deposit_amount INT DEFAULT NULL');
        $this->addSql('ALTER TABLE orders ADD paid_amount INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE orders ADD paid_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE orders ADD reschedule_count SMALLINT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE orders ADD rescheduled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE orders ADD promo_code_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE orders ADD gift_certificate_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT FK_E52FFDEE2FAE4625 FOREIGN KEY (promo_code_id) REFERENCES promo_codes (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT FK_E52FFDEEA97C76A8 FOREIGN KEY (gift_certificate_id) REFERENCES gift_certificates (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_E52FFDEE2FAE4625 ON orders (promo_code_id)');
        $this->addSql('CREATE INDEX IDX_E52FFDEEA97C76A8 ON orders (gift_certificate_id)');
        $this->addSql('ALTER TABLE users ADD totp_secret VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD totp_enabled BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE users ADD backup_codes JSON DEFAULT \'[]\' NOT NULL');
        $this->addSql('ALTER TABLE users ADD trusted_version INT DEFAULT 0 NOT NULL');

        // Existing bookings: the old estimate was the trip price.
        $this->addSql('UPDATE orders SET base_price = estimated_price');

        // The FAQ used to live in translation files; it is now edited in the admin panel.
        foreach (self::FAQ as $position => $item) {
            $this->addSql(
                'INSERT INTO faq_items (question, answer, position, show_on_home, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, true, NOW(), NOW())',
                [
                    json_encode($item['question'], \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR),
                    json_encode($item['answer'], \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR),
                    $position + 1,
                    \in_array($position, [0, 1, 3], true),
                ],
                [\Doctrine\DBAL\ParameterType::STRING, \Doctrine\DBAL\ParameterType::STRING, \Doctrine\DBAL\ParameterType::INTEGER, \Doctrine\DBAL\ParameterType::BOOLEAN],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE messenger_subscriptions DROP CONSTRAINT FK_BB30FFC3A76ED395');
        $this->addSql('ALTER TABLE messenger_subscriptions DROP CONSTRAINT FK_BB30FFC38D9F6D38');
        $this->addSql('ALTER TABLE order_extras DROP CONSTRAINT FK_E51475048D9F6D38');
        $this->addSql('ALTER TABLE order_extras DROP CONSTRAINT FK_E51475042B959FC6');
        $this->addSql('ALTER TABLE payments DROP CONSTRAINT FK_65D29B328D9F6D38');
        $this->addSql('ALTER TABLE payments DROP CONSTRAINT FK_65D29B32A97C76A8');
        $this->addSql('ALTER TABLE posts DROP CONSTRAINT FK_885DBAFA15ED8D43');
        $this->addSql('ALTER TABLE waitlist_entries DROP CONSTRAINT FK_E74550EE15ED8D43');
        $this->addSql('DROP TABLE audit_log');
        $this->addSql('DROP TABLE extras');
        $this->addSql('DROP TABLE faq_items');
        $this->addSql('DROP TABLE gift_certificates');
        $this->addSql('DROP TABLE messenger_subscriptions');
        $this->addSql('DROP TABLE order_extras');
        $this->addSql('DROP TABLE payments');
        $this->addSql('DROP TABLE posts');
        $this->addSql('DROP TABLE promo_codes');
        $this->addSql('DROP TABLE waitlist_entries');
        $this->addSql('ALTER TABLE orders DROP CONSTRAINT FK_E52FFDEE2FAE4625');
        $this->addSql('ALTER TABLE orders DROP CONSTRAINT FK_E52FFDEEA97C76A8');
        $this->addSql('DROP INDEX IDX_E52FFDEE2FAE4625');
        $this->addSql('DROP INDEX IDX_E52FFDEEA97C76A8');
        $this->addSql('ALTER TABLE orders DROP base_price');
        $this->addSql('ALTER TABLE orders DROP extras_price');
        $this->addSql('ALTER TABLE orders DROP discount');
        $this->addSql('ALTER TABLE orders DROP gift_amount');
        $this->addSql('ALTER TABLE orders DROP deposit_amount');
        $this->addSql('ALTER TABLE orders DROP paid_amount');
        $this->addSql('ALTER TABLE orders DROP paid_at');
        $this->addSql('ALTER TABLE orders DROP reschedule_count');
        $this->addSql('ALTER TABLE orders DROP rescheduled_at');
        $this->addSql('ALTER TABLE orders DROP promo_code_id');
        $this->addSql('ALTER TABLE orders DROP gift_certificate_id');
        $this->addSql('ALTER TABLE users DROP totp_secret');
        $this->addSql('ALTER TABLE users DROP totp_enabled');
        $this->addSql('ALTER TABLE users DROP backup_codes');
        $this->addSql('ALTER TABLE users DROP trusted_version');
    }
}
