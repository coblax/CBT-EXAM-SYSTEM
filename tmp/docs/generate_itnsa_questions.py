from __future__ import annotations

import json
import shutil
import zipfile
from pathlib import Path
import xml.etree.ElementTree as ET


W_NS = "http://schemas.openxmlformats.org/wordprocessingml/2006/main"
NS = {"w": W_NS}
ET.register_namespace("w", W_NS)

BASE_DIR = Path("/var/www/wordpress/wp-content/plugins/cbt-exam-system")
TEMPLATE_PATH = BASE_DIR / "templates" / "SOAL.docx"
OUTPUT_DIR = BASE_DIR / "output" / "doc"
OUTPUT_DOCX = OUTPUT_DIR / "SOAL_ITNSA_2024_70_PG.docx"
OUTPUT_KEY = OUTPUT_DIR / "SOAL_ITNSA_2024_70_PG_kunci_jawaban.txt"
OUTPUT_META = OUTPUT_DIR / "SOAL_ITNSA_2024_70_PG_metadata.json"

PLAYLIST_URL = "https://youtube.com/playlist?list=PLbu33igsurMSA9sSgHm64LRN84NxEYBkD&si=7N2qx6FSLBTjyXHu"
PLAYLIST_TOPICS = [
    "INSTALL LINUX DEBIAN 13.0",
    "SETUP TOPOLOGY - CLONE VIRTUAL MACHINE",
    "SETUP TOPOLOGY - LAN SEGMENT",
    "IP ADDRESSING",
    "FULLY QUALIFIED DOMAIN NAME (FQDN)",
    "LINUX OS - SET TIMEZONE",
    "CONFIGURE THE NAMESERVER USING RESOLVCONF",
    "LINUX OS - DHCP SERVER",
    "LINUX OS - IPV4 FORWARDING",
    "LINUX OS - DNS SERVER FORWARD_ZONE",
    "LINUX OS - DNS SERVER REVERSE ZONE",
    "LINUX OS - DNS SERVER SLAVE",
    "LINUX OS - CONFIGURING SELF-SIGNED ROOT CERTIFICATE AUTHORITY",
    "LINUX OS - DISTRIBUTE AND TRUST ROOT CERTIFICATES",
    "LINUX OS - MANUAL CERTIFICATE SIGNING WITH ROOT CA",
    "LINUX OS - BASH SCRIPTING: AUTO-SIGN CERTIFICATES WITH ROOT CA",
    "LINUX OS - APACHE2 WEB SERVER: CONFIGURING EAST.ITNSA.ID",
    "LINUX OS - NGINX WEB SERVER: CONFIGURING WEST.ITNSA.ID",
    "LINUX OS - NGINX: HTTPS WITH SSL VERIFICATION",
    "LINUX OS - APACHE2: HTTPS WITH SSL VERIFICATION",
    "LINUX OS - HAPROXY LOAD BALANCER: HTTPS CONFIGURATION",
    "LINUX OS - LDAP SERVICE: CENTRALIZED AUTHENTICATION SETUP",
    "LINUX OS - BASH SCRIPTING: ADD LDAP USER OBJECT",
    "LINUX OS - BASH SCRIPTING: DELETE LDAP USER OBJECT",
    "LINUX OS - SAMBA DATABASE: ADD AUTHENTICATED USERS",
    "LINUX OS - SAMBA FILE SERVICE: PUBLIC & PRIVATE SHARED FOLDER",
    "LINUX OS - BASH SCRIPTING: SYNC LDAP USERS TO SAMBA",
    "LINUX OS - MAIL AUTHENTICATION: PAM & LDAP INTEGRATION ON WSSRV",
    "LINUX OS - MAIL SERVICE: DOVECOT IMAP & SSL CONFIGURATION",
    "LINUX OS - MAIL SERVICE: POSTFIX SMTP & SUBMISSION CONFIGURATION",
]


def q(question: str, options: list[str], answer: int, explanation: str) -> dict[str, object]:
    assert len(options) == 5, question
    assert 1 <= answer <= 5, question
    assert len(set(options)) == 5, question
    return {
        "question": question,
        "options": options,
        "answer": answer,
        "points": 1,
        "explanation": explanation,
    }


QUESTIONS = [
    q(
        "Tujuan utama file ISO Debian saat proses instalasi adalah ...",
        [
            "menyimpan log firewall server",
            "menjadi media instalasi sistem operasi",
            "menyinkronkan record DNS internal",
            "membuat sertifikat root CA",
            "menampung database LDAP",
        ],
        2,
        "File ISO berisi image instalasi yang dipakai untuk memasang sistem operasi Debian ke VM atau server.",
    ),
    q(
        "Alasan melakukan clone virtual machine sebelum membangun topologi adalah ...",
        [
            "agar semua node memakai IP yang sama",
            "supaya DHCP server aktif otomatis",
            "mempercepat penyediaan beberapa node dengan konfigurasi dasar yang sama",
            "menghapus kebutuhan konfigurasi jaringan",
            "mengubah Debian menjadi appliance read-only",
        ],
        3,
        "Cloning dipakai untuk menggandakan mesin dasar sehingga node lain cepat dibuat dengan baseline yang seragam.",
    ),
    q(
        "Jika dua host berada pada LAN segment yang berbeda, perangkat minimum agar keduanya bisa saling berkomunikasi adalah ...",
        [
            "switch layer-2 biasa",
            "hub pasif",
            "router atau perangkat layer-3",
            "patch panel",
            "UPS online",
        ],
        3,
        "Komunikasi lintas segmen butuh fungsi routing, sehingga diperlukan router atau perangkat layer-3.",
    ),
    q(
        "Subnet mask yang setara dengan prefix /24 adalah ...",
        [
            "255.255.255.0",
            "255.255.0.0",
            "255.0.0.0",
            "255.255.255.128",
            "255.255.254.0",
        ],
        1,
        "Prefix /24 berarti 24 bit pertama dipakai untuk network sehingga subnet mask-nya 255.255.255.0.",
    ),
    q(
        "Network address dari host 192.168.10.25/24 adalah ...",
        [
            "192.168.10.1",
            "192.168.10.24",
            "192.168.10.255",
            "192.168.10.0",
            "192.168.0.0",
        ],
        4,
        "Pada jaringan /24, network address diperoleh dari host dengan semua bit host di-nol-kan, yaitu 192.168.10.0.",
    ),
    q(
        "Pada FQDN east.itnsa.id, bagian yang berperan sebagai hostname adalah ...",
        [
            "id",
            "east",
            "itnsa",
            "east.itnsa",
            "itnsa.id",
        ],
        2,
        "FQDN memuat hostname dan domain lengkap; pada contoh ini hostname-nya adalah east.",
    ),
    q(
        "Perintah Linux yang umum digunakan untuk mengatur timezone server adalah ...",
        [
            "ip addr set timezone Asia/Jakarta",
            "resolvectl timezone Asia/Jakarta",
            "hostnamectl set-time Asia/Jakarta",
            "systemctl enable timezone Asia/Jakarta",
            "timedatectl set-timezone Asia/Jakarta",
        ],
        5,
        "Pengaturan zona waktu modern pada Linux umumnya dilakukan dengan timedatectl set-timezone.",
    ),
    q(
        "Timezone server harus benar terutama karena akan memengaruhi ...",
        [
            "kapasitas RAM virtual machine",
            "jumlah interface jaringan",
            "akurasi log, penjadwalan, dan validitas waktu sertifikat",
            "ukuran partisi root",
            "nama host FQDN",
        ],
        3,
        "Waktu sistem yang keliru dapat membuat log tidak sinkron dan sertifikat dianggap belum valid atau sudah kedaluwarsa.",
    ),
    q(
        "Fungsi utama resolvconf pada Linux adalah ...",
        [
            "mengelola konfigurasi resolver dan menghasilkan /etc/resolv.conf",
            "mengaktifkan DHCP server pada setiap interface",
            "membagi trafik HTTPS ke beberapa backend",
            "mengatur virtual host Apache",
            "membuat reverse zone DNS",
        ],
        1,
        "resolvconf membantu mengelola nameserver dan search domain lalu menuliskannya ke file resolver aktif.",
    ),
    q(
        "Jika pada lab tersedia DNS internal, nameserver pada client sebaiknya diarahkan ke ...",
        [
            "IP salah satu client acak",
            "broadcast address jaringan",
            "IP gateway publik ISP",
            "IP server DNS internal",
            "loopback 127.0.0.1 milik client",
        ],
        4,
        "Client sebaiknya menggunakan DNS internal agar resolusi domain lab berjalan sesuai topologi dan zone yang dibuat.",
    ),
    q(
        "Fungsi utama DHCP server di jaringan adalah ...",
        [
            "menandatangani sertifikat TLS",
            "membagikan konfigurasi IP secara otomatis ke client",
            "menyimpan mailbox user",
            "menyalin zone DNS dari master",
            "mengelola share Samba public",
        ],
        2,
        "DHCP melayani pemberian IP address beserta parameter jaringan lain secara otomatis kepada client.",
    ),
    q(
        "Informasi yang lazim diberikan DHCP kepada client adalah ...",
        [
            "UID LDAP, password root, dan DN user",
            "FQDN, PTR, dan serial zone",
            "sertifikat publik, private key, dan CSR",
            "mailbox path, alias email, dan queue mail",
            "IP address, subnet mask, gateway, dan DNS",
        ],
        5,
        "DHCP biasanya mengirim IP address bersama parameter penting seperti subnet mask, default gateway, dan nameserver.",
    ),
    q(
        "IPv4 forwarding perlu diaktifkan ketika server harus ...",
        [
            "meneruskan paket antar interface atau jaringan yang berbeda",
            "menjadi DNS slave",
            "menyimpan kunci privat root CA",
            "menambahkan user ke LDAP",
            "membaca mailbox IMAP",
        ],
        1,
        "IP forwarding memungkinkan kernel Linux meneruskan paket dari satu interface ke interface lain.",
    ),
    q(
        "Agar IPv4 forwarding aktif melalui sysctl, nilai net.ipv4.ip_forward harus diatur menjadi ...",
        [
            "0",
            "1",
            "24",
            "53",
            "255",
        ],
        2,
        "Nilai 1 mengaktifkan forwarding paket IPv4, sedangkan 0 menonaktifkannya.",
    ),
    q(
        "Forward zone pada DNS digunakan untuk ...",
        [
            "memetakan nama domain menjadi alamat IP",
            "meneruskan paket antar subnet",
            "mendistribusikan sertifikat root CA",
            "membuat mailbox user secara otomatis",
            "mengatur autentikasi PAM",
        ],
        1,
        "Forward zone berisi record yang memetakan nama host/domain ke alamat IP tujuan.",
    ),
    q(
        "Record DNS yang umum dipakai untuk memetakan hostname ke alamat IPv4 adalah ...",
        [
            "MX",
            "NS",
            "PTR",
            "A",
            "TXT",
        ],
        4,
        "Record A menyimpan pemetaan nama host ke alamat IPv4.",
    ),
    q(
        "Reverse zone pada DNS digunakan untuk ...",
        [
            "menentukan gateway default",
            "mengganti hostname Linux",
            "mengelola user LDAP",
            "membuat virtual host Apache",
            "menerjemahkan IP address menjadi nama host",
        ],
        5,
        "Reverse zone dipakai saat melakukan reverse lookup dari IP address ke nama host melalui record PTR.",
    ),
    q(
        "Nama reverse zone yang benar untuk jaringan 192.168.10.0/24 adalah ...",
        [
            "192.168.10.in-addr.arpa",
            "10.192.168.in-addr.arpa",
            "10.168.192.in-addr.arpa",
            "192.10.168.in-addr.arpa",
            "arpa.192.168.10",
        ],
        3,
        "Untuk /24, tiga oktet network dibalik urutannya menjadi 10.168.192.in-addr.arpa.",
    ),
    q(
        "DNS slave memperoleh salinan data zone dari server master melalui ...",
        [
            "ARP request",
            "zone transfer",
            "mail submission",
            "LDAP bind",
            "Samba browsing",
        ],
        2,
        "Slave DNS melakukan zone transfer dari master agar memiliki salinan zone yang sama.",
    ),
    q(
        "Keuntungan utama menambahkan DNS slave pada topologi adalah ...",
        [
            "mengurangi kebutuhan record PTR",
            "menghapus kewajiban setting gateway",
            "menambah jumlah private key root CA",
            "memberi redundansi dan meningkatkan ketersediaan layanan DNS",
            "mengubah IMAP menjadi SMTP",
        ],
        4,
        "Slave DNS memberi cadangan layanan resolusi ketika master bermasalah atau tidak dapat dijangkau.",
    ),
    q(
        "Dalam skenario lab ini, root Certificate Authority berfungsi untuk ...",
        [
            "menandatangani sertifikat server dan client",
            "membuat IP pool DHCP",
            "menjadi backend HAProxy",
            "menyimpan folder private Samba",
            "menjalankan postfix queue",
        ],
        1,
        "Root CA dipakai sebagai sumber kepercayaan yang menandatangani sertifikat layanan internal.",
    ),
    q(
        "Kunci yang wajib dijaga paling ketat pada lingkungan root CA adalah ...",
        [
            "serial number sertifikat",
            "file zone reverse DNS",
            "alamat IP nameserver",
            "port submission postfix",
            "private key milik root CA",
        ],
        5,
        "Jika private key root CA bocor, seluruh kepercayaan terhadap sertifikat yang diterbitkan akan runtuh.",
    ),
    q(
        "Sebelum sertifikat server ditandatangani secara manual oleh root CA, administrator biasanya membuat ...",
        [
            "A record",
            "Samba share",
            "Certificate Signing Request",
            "zone transfer",
            "mail relay rule",
        ],
        3,
        "CSR memuat identitas subjek dan public key yang nantinya akan ditandatangani CA menjadi sertifikat.",
    ),
    q(
        "Agar client mempercayai sertifikat yang ditandatangani root CA internal, admin perlu ...",
        [
            "mengaktifkan DHCP reservation",
            "mendistribusikan root certificate ke trust store client",
            "mengubah semua domain menjadi localhost",
            "menonaktifkan verifikasi TLS di browser",
            "memindahkan mailbox ke /tmp",
        ],
        2,
        "Root certificate harus dimasukkan ke trust store client agar rantai kepercayaan dikenali valid.",
    ),
    q(
        "Keuntungan menggunakan bash scripting untuk auto-sign certificates adalah ...",
        [
            "mengganti fungsi DNS master",
            "membatasi jumlah virtual host web",
            "menambah kapasitas storage Samba",
            "mengotomatiskan proses berulang dan mengurangi human error",
            "memindahkan akun LDAP ke Postfix",
        ],
        4,
        "Otomasi membantu proses signing massal berjalan konsisten dan lebih cepat dibanding langkah manual berulang.",
    ),
    q(
        "Directive Apache yang digunakan untuk menyatakan nama host utama virtual host adalah ...",
        [
            "ServerName",
            "RootCAName",
            "ListenTLS",
            "DocumentAlias",
            "ProxyNode",
        ],
        1,
        "Pada Apache, ServerName dipakai untuk menentukan nama host utama yang dilayani virtual host tersebut.",
    ),
    q(
        "Pada Nginx, domain yang dilayani oleh sebuah server block umumnya ditentukan melalui directive ...",
        [
            "root_name",
            "listen_name",
            "virtual_host",
            "host_alias",
            "server_name",
        ],
        5,
        "Nginx menggunakan directive server_name untuk mencocokkan nama host yang masuk ke server block terkait.",
    ),
    q(
        "Directive Apache yang menunjuk direktori konten website adalah ...",
        [
            "ServerPath",
            "DocumentRoot",
            "ProxyPass",
            "AliasMatch",
            "ServerAdmin",
        ],
        2,
        "DocumentRoot menentukan direktori dasar tempat file website disajikan oleh Apache.",
    ),
    q(
        "Agar Nginx dapat melayani HTTPS, pasangan berkas penting yang harus dikonfigurasi adalah ...",
        [
            "file LDIF dan file PAM",
            "zone file dan resolv.conf",
            "mailbox dan alias map",
            "sertifikat publik dan private key",
            "DHCP lease dan ARP cache",
        ],
        4,
        "HTTPS membutuhkan sertifikat beserta private key yang sesuai agar TLS handshake berhasil.",
    ),
    q(
        "Agar virtual host Apache benar-benar melayani HTTPS, konfigurasi yang harus aktif adalah ...",
        [
            "AllowEncodedSlashes On",
            "ProxyRequests On",
            "SSLEngine on",
            "NameVirtualHost *:443",
            "ServerTokens Full",
        ],
        3,
        "SSLEngine on mengaktifkan pemrosesan SSL/TLS pada virtual host Apache yang melayani HTTPS.",
    ),
    q(
        "Tujuan SSL verification pada layanan HTTPS adalah ...",
        [
            "memastikan identitas server tervalidasi dan trafik terenkripsi",
            "mengubah IMAP menjadi SMTP",
            "mendistribusikan IP secara dinamis",
            "menambah jumlah user Samba",
            "menghapus kebutuhan DNS reverse zone",
        ],
        1,
        "Verifikasi TLS memastikan client benar-benar berbicara dengan server yang sah sambil mengenkripsi sesi.",
    ),
    q(
        "Fungsi utama HAProxy pada skenario load balancer adalah ...",
        [
            "menggandakan root CA",
            "mendistribusikan trafik ke beberapa backend",
            "mengganti resolver pada /etc/resolv.conf",
            "menyimpan user LDAP",
            "mengirim email submission",
        ],
        2,
        "HAProxy menerima trafik dari frontend lalu membagi permintaan ke beberapa server backend sesuai konfigurasi.",
    ),
    q(
        "Jika terminasi HTTPS dilakukan di HAProxy, pasangan sertifikat publik dan private key dapat dipasang pada ...",
        [
            "server DHCP",
            "zone reverse DNS",
            "user object LDAP",
            "mailbox Dovecot",
            "frontend atau listener HAProxy",
        ],
        5,
        "Saat SSL termination terjadi di HAProxy, sertifikat dipakai pada frontend/listener yang menerima koneksi client.",
    ),
    q(
        "Manfaat penggunaan load balancer untuk layanan ujian berbasis web adalah ...",
        [
            "menghapus kebutuhan web server backend",
            "mengurangi fungsi autentikasi LDAP",
            "meningkatkan ketersediaan layanan dan membagi beban trafik",
            "mengganti fungsi DNS zone",
            "menonaktifkan TLS verification",
        ],
        3,
        "Load balancer membantu distribusi beban dan menjaga layanan tetap tersedia ketika trafik tinggi.",
    ),
    q(
        "LDAP pada skenario ini berperan sebagai ...",
        [
            "server proxy cache",
            "router antar LAN segment",
            "server monitoring waktu",
            "direktori terpusat akun dan atribut user",
            "file share publik",
        ],
        4,
        "LDAP menyimpan identitas user secara terpusat sehingga dapat dipakai ulang oleh banyak layanan.",
    ),
    q(
        "Keuntungan autentikasi terpusat dengan LDAP adalah ...",
        [
            "sertifikat TLS tidak lagi dibutuhkan",
            "satu akun dapat dipakai lintas layanan yang terintegrasi",
            "setiap service wajib punya password lokal berbeda",
            "DNS slave otomatis hilang",
            "HAProxy tidak perlu backend",
        ],
        2,
        "Autentikasi terpusat memudahkan administrasi karena satu identitas bisa dipakai pada banyak layanan.",
    ),
    q(
        "Distinguished Name (DN) pada LDAP digunakan untuk ...",
        [
            "mengidentifikasi objek secara unik dalam tree direktori",
            "menghitung subnet mask",
            "menentukan port IMAPS",
            "mengatur record A website",
            "menyimpan file konfigurasi HAProxy",
        ],
        1,
        "DN adalah jalur unik yang menunjukkan posisi sebuah objek di pohon direktori LDAP.",
    ),
    q(
        "Perintah yang umum dipakai untuk menambahkan objek LDAP dari file LDIF adalah ...",
        [
            "ldapsearch",
            "ldapwhoami",
            "ldapadd",
            "ldapdelete",
            "ldappasswd",
        ],
        3,
        "ldapadd membaca entri LDIF lalu menambahkannya ke direktori LDAP.",
    ),
    q(
        "Perintah yang lazim dipakai untuk menghapus objek LDAP adalah ...",
        [
            "ldapmodify",
            "ldapcompare",
            "slapcat",
            "ldapdelete",
            "id",
        ],
        4,
        "ldapdelete digunakan untuk menghapus entri tertentu dari direktori LDAP berdasarkan DN-nya.",
    ),
    q(
        "Saat menambahkan user LDAP, nilai yang harus unik agar tidak bertabrakan adalah ...",
        [
            "nama zone DNS",
            "nomor port IMAPS",
            "nilai subnet mask",
            "nama backend HAProxy",
            "uid atau DN milik user tersebut",
        ],
        5,
        "uid dan DN harus unik agar objek user dapat dibedakan dengan jelas di dalam direktori.",
    ),
    q(
        "Pada konteks Samba database, menambahkan authenticated users bertujuan agar ...",
        [
            "setiap share menjadi public",
            "akun dikenali saat autentikasi ke layanan SMB",
            "DNS reverse zone diperbarui otomatis",
            "sertifikat HTTPS dibuat ulang",
            "zone transfer DNS aktif",
        ],
        2,
        "Samba perlu mengenali akun yang akan dipakai saat user melakukan autentikasi ke share SMB.",
    ),
    q(
        "Perbedaan utama shared folder private dibanding public pada Samba adalah ...",
        [
            "aksesnya dibatasi oleh autentikasi dan izin tertentu",
            "harus selalu memakai port 587",
            "hanya bisa dipakai di DNS slave",
            "tidak boleh berada di Linux",
            "selalu wajib tanpa password",
        ],
        1,
        "Private share dirancang agar hanya user atau grup tertentu yang dapat mengaksesnya.",
    ),
    q(
        "Cara paling tepat melindungi private share Samba adalah ...",
        [
            "cukup mengganti timezone server",
            "menghapus semua user dari LDAP",
            "mengaktifkan IPv4 forwarding",
            "menggabungkan permission filesystem dan aturan akses pada share Samba",
            "memindahkan zone reverse ke /home",
        ],
        4,
        "Keamanan private share harus dijaga dari dua sisi: izin di filesystem dan aturan autentikasi/akses Samba.",
    ),
    q(
        "Sinkronisasi LDAP users ke Samba diperlukan agar ...",
        [
            "klien mendapat IP dari DHCP",
            "sertifikat root CA bisa diperpanjang",
            "HAProxy mengenali seluruh backend web",
            "Postfix dapat membaca mailbox Dovecot",
            "akun dari direktori terpusat juga tersedia untuk autentikasi SMB",
        ],
        5,
        "Sinkronisasi ini membuat identitas user terpusat di LDAP tetap bisa dipakai saat login ke Samba.",
    ),
    q(
        "PAM pada Linux berfungsi sebagai ...",
        [
            "framework modul autentikasi sistem",
            "server DNS caching",
            "load balancer layer-7",
            "mailbox storage backend",
            "generator sertifikat root CA",
        ],
        1,
        "PAM menyediakan mekanisme modular bagi berbagai aplikasi Linux untuk melakukan autentikasi.",
    ),
    q(
        "Integrasi PAM dan LDAP pada WSSRV membuat user dapat ...",
        [
            "login dengan kredensial LDAP tanpa harus membuat akun lokal baru per service",
            "menambahkan zone slave lewat IMAP",
            "menghapus postfix queue lewat Samba",
            "menjalankan HTTPS tanpa sertifikat",
            "mengganti IP client tanpa DHCP",
        ],
        1,
        "Dengan PAM dan LDAP, autentikasi sistem bisa menggunakan akun terpusat dari direktori LDAP.",
    ),
    q(
        "Dovecot pada arsitektur mail umumnya menangani layanan ...",
        [
            "routing antar subnet",
            "DHCP reservation",
            "penandatanganan sertifikat",
            "akses mailbox melalui IMAP atau POP3",
            "sinkronisasi user ke Samba",
        ],
        4,
        "Dovecot biasa dipakai untuk layanan akses mailbox seperti IMAP atau POP3.",
    ),
    q(
        "Port default untuk layanan IMAPS (IMAP over SSL/TLS) adalah ...",
        [
            "25",
            "993",
            "587",
            "110",
            "53",
        ],
        2,
        "IMAPS secara umum berjalan pada port 993 untuk koneksi yang langsung terenkripsi.",
    ),
    q(
        "Postfix pada arsitektur mail umumnya berperan sebagai ...",
        [
            "server SMTP",
            "server DHCP",
            "server LDAP",
            "server DNS authoritative",
            "server file sharing",
        ],
        1,
        "Postfix adalah MTA yang menangani pengiriman dan penerimaan email berbasis SMTP.",
    ),
    q(
        "Port yang lazim digunakan layanan submission untuk client mail terautentikasi adalah ...",
        [
            "443",
            "995",
            "587",
            "53",
            "161",
        ],
        3,
        "Port 587 dipakai untuk submission oleh client mail yang melakukan autentikasi ke server SMTP.",
    ),
    q(
        "Keuntungan clone VM dibanding install ulang manual untuk setiap node adalah ...",
        [
            "penyediaan node baru menjadi lebih cepat dengan baseline identik",
            "setiap node otomatis punya IP publik",
            "DNS reverse zone tidak lagi dibutuhkan",
            "TLS verification dimatikan otomatis",
            "Samba public share terpasang otomatis",
        ],
        1,
        "Cloning mempercepat penyiapan beberapa mesin dengan konfigurasi dasar yang sama persis.",
    ),
    q(
        "Pemisahan jaringan ke beberapa LAN segment bermanfaat karena ...",
        [
            "menghilangkan kebutuhan routing",
            "menyatukan semua broadcast domain",
            "membuat semua host berada di subnet yang sama",
            "membatasi broadcast domain dan memudahkan pengelolaan topologi",
            "menghapus peran DNS",
        ],
        4,
        "Segmentasi LAN membantu mengendalikan broadcast dan membuat topologi lebih terstruktur.",
    ),
    q(
        "Perbedaan FQDN dengan hostname biasa adalah ...",
        [
            "FQDN mencakup nama host dan domain lengkap",
            "hostname selalu berisi alamat IPv4",
            "FQDN hanya berlaku untuk mail server",
            "hostname selalu terdiri dari tiga label",
            "FQDN tidak boleh dipakai di DNS",
        ],
        1,
        "Hostname hanya nama host-nya saja, sedangkan FQDN memuat host plus domain penuh.",
    ),
    q(
        "Jika timezone server salah jauh dari waktu sebenarnya, dampak yang mungkin muncul pada layanan SSL adalah ...",
        [
            "client mendapat subnet mask baru",
            "reverse zone langsung rusak",
            "port SMTP berubah otomatis",
            "share Samba berubah menjadi read-only",
            "sertifikat dapat terlihat belum valid atau sudah kedaluwarsa",
        ],
        5,
        "TLS sangat sensitif terhadap waktu karena masa berlaku sertifikat diperiksa berdasarkan tanggal dan jam sistem.",
    ),
    q(
        "Praktik baik saat mengatur nameserver melalui resolvconf adalah ...",
        [
            "mengisi hanya loopback tanpa DNS cadangan",
            "menyalin private key root CA ke /etc/resolv.conf",
            "menyediakan nameserver utama dan cadangan yang valid",
            "mengisi alamat broadcast sebagai DNS",
            "mematikan semua record PTR",
        ],
        3,
        "Adanya nameserver utama dan cadangan membantu menjaga resolusi tetap berjalan saat satu server bermasalah.",
    ),
    q(
        "DHCP reservation dipakai ketika administrator ingin ...",
        [
            "memberikan IP tetap ke perangkat tertentu berdasarkan MAC address",
            "menandatangani CSR server web",
            "membuat server block Nginx",
            "menghapus objek LDAP",
            "mengatur port submission",
        ],
        1,
        "Reservation memastikan perangkat tertentu selalu memperoleh alamat IP yang sama dari DHCP.",
    ),
    q(
        "Mengaktifkan IPv4 forwarding saja belum tentu cukup untuk berbagi akses internet karena mungkin masih diperlukan ...",
        [
            "routing atau NAT tambahan sesuai desain topologi",
            "record PTR pada semua host",
            "virtual host Apache kedua",
            "pemindahan user ke Dovecot",
            "penghapusan server slave DNS",
        ],
        1,
        "Forwarding hanya mengizinkan paket diteruskan; translasi atau kebijakan routing tambahan bisa tetap dibutuhkan.",
    ),
    q(
        "Record NS pada forward zone menunjukkan ...",
        [
            "mail exchanger domain",
            "server DNS yang otoritatif untuk zone tersebut",
            "alamat IP client DHCP",
            "private key yang dipakai Apache",
            "nama user Samba",
        ],
        2,
        "Record NS dipakai untuk menyatakan nameserver yang berwenang terhadap sebuah zone DNS.",
    ),
    q(
        "Hasil reverse lookup yang benar biasanya berupa ...",
        [
            "subnet mask dari server",
            "serial number sertifikat",
            "gateway default jaringan",
            "isi file resolv.conf",
            "nama host atau FQDN dari sebuah alamat IP",
        ],
        5,
        "Reverse lookup melalui PTR mengembalikan nama host yang berasosiasi dengan alamat IP tersebut.",
    ),
    q(
        "Client atau browser akan berhenti memberi peringatan CA tidak dikenal jika ...",
        [
            "DHCP server dimatikan",
            "port 53 diblokir",
            "Postfix dijalankan di port 25",
            "root certificate internal sudah dipercaya di trust store",
            "host memakai IP statis",
        ],
        4,
        "Peringatan trust akan hilang jika CA penerbit sertifikat sudah dipercaya oleh sistem atau browser client.",
    ),
    q(
        "Hasil dari proses penandatanganan CSR oleh root CA adalah ...",
        [
            "mail relay rule",
            "zone transfer request",
            "sertifikat server yang ditandatangani CA",
            "public share baru",
            "subnet mask /24",
        ],
        3,
        "CSR yang ditandatangani CA menghasilkan sertifikat server siap pakai untuk layanan TLS.",
    ),
    q(
        "Istilah blok konfigurasi virtual host pada Nginx dikenal sebagai ...",
        [
            "server block",
            "backend pool",
            "bind request",
            "mail queue",
            "tree branch",
        ],
        1,
        "Pada Nginx, setiap situs atau layanan biasanya didefinisikan di dalam server block.",
    ),
    q(
        "Daftar server tujuan yang akan menerima trafik dari HAProxy biasanya diletakkan pada seksi ...",
        [
            "frontend",
            "backend",
            "global",
            "defaults",
            "resolver",
        ],
        2,
        "Seksi backend berisi daftar server aplikasi yang menjadi tujuan distribusi trafik dari HAProxy.",
    ),
    q(
        "Object class pada LDAP menentukan ...",
        [
            "versi kernel Debian",
            "jumlah DHCP lease aktif",
            "warna tema web server",
            "urutan zone transfer",
            "atribut dan struktur yang boleh dimiliki objek",
        ],
        5,
        "Object class mendefinisikan skema atribut yang valid untuk sebuah entri LDAP.",
    ),
    q(
        "Saat menghapus user LDAP, administrator sebaiknya juga memeriksa ...",
        [
            "dependensi ke layanan lain agar tidak ada akses yatim",
            "warna border kartu nomor meja",
            "urutan boot virtual machine",
            "ukuran file ISO Debian",
            "jumlah halaman dokumen Word",
        ],
        1,
        "Penghapusan akun terpusat bisa berdampak ke banyak layanan, sehingga dependensinya perlu diperiksa lebih dulu.",
    ),
    q(
        "Share Samba yang paling longgar aksesnya umumnya adalah ...",
        [
            "private share",
            "public share",
            "SSL share",
            "PTR share",
            "reverse share",
        ],
        2,
        "Public share biasanya dirancang dengan pembatasan paling longgar dibanding private share.",
    ),
    q(
        "Mengaktifkan SSL/TLS pada Dovecot terutama berguna untuk ...",
        [
            "membuat DNS slave",
            "melindungi kredensial dan sesi akses mailbox saat transit",
            "mendistribusikan alamat IP",
            "mengganti fungsi HAProxy",
            "membuat user LDAP baru",
        ],
        2,
        "TLS pada Dovecot menjaga username, password, dan data mailbox tetap terenkripsi di jaringan.",
    ),
    q(
        "Layanan submission Postfix ditujukan terutama untuk ...",
        [
            "zone transfer antar DNS",
            "sinkronisasi user Samba",
            "client mail terautentikasi yang mengirim email",
            "akses file public share",
            "pengaturan timezone server",
        ],
        3,
        "Submission dipakai oleh mail client yang melakukan autentikasi sebelum mengirim email melalui SMTP.",
    ),
    q(
        "Integrasi PAM dan LDAP mengurangi ...",
        [
            "kebutuhan record MX",
            "jumlah backend HAProxy",
            "penggunaan port 443",
            "duplikasi pengelolaan akun lokal di banyak server",
            "kebutuhan TLS pada IMAP",
        ],
        4,
        "Dengan autentikasi terpusat, admin tidak perlu memelihara akun lokal terpisah di setiap layanan atau server.",
    ),
    q(
        "Pada arsitektur mail di playlist ini, komponen yang menyimpan identitas user secara terpusat adalah ...",
        [
            "Dovecot",
            "Postfix",
            "HAProxy",
            "LDAP",
            "Apache2",
        ],
        4,
        "LDAP berperan sebagai sumber identitas user terpusat yang dipakai oleh layanan lain untuk autentikasi.",
    ),
    q(
        "Jika administrator ingin sebuah perangkat selalu memperoleh alamat yang sama dari DHCP, fitur yang dipakai adalah ...",
        [
            "zone delegation",
            "certificate pinning",
            "mail relay",
            "DHCP reservation",
            "LDAP replication",
        ],
        4,
        "DHCP reservation mengikat alamat IP tertentu ke perangkat tertentu biasanya berdasarkan MAC address.",
    ),
    q(
        "Ketika membuat website east.itnsa.id pada Apache, kecocokan nama host permintaan HTTP terutama ditentukan oleh ...",
        [
            "ServerName",
            "DocumentRoot",
            "ProxyPreserveHost",
            "ServerTokens",
            "Timeout",
        ],
        1,
        "Apache mencocokkan host yang dilayani melalui ServerName atau ServerAlias pada virtual host terkait.",
    ),
    q(
        "Pada Nginx, satu server block dapat melayani domain tertentu karena adanya directive ...",
        [
            "listen_only",
            "server_name",
            "ssl_client_verify",
            "include_root",
            "mime_types",
        ],
        2,
        "server_name menentukan nama host yang akan dipetakan ke server block tertentu di Nginx.",
    ),
    q(
        "Apabila sertifikat root CA internal belum diinstal pada client, akses HTTPS ke web internal biasanya akan ...",
        [
            "tetap sukses tanpa peringatan",
            "mengubah DNS A record otomatis",
            "memunculkan peringatan trust sertifikat",
            "menghapus private key server",
            "menonaktifkan IMAP SSL",
        ],
        3,
        "Tanpa root CA yang dipercaya, client tidak dapat memverifikasi rantai kepercayaan sertifikat internal.",
    ),
    q(
        "Perintah ldapadd paling umum digunakan bersama berkas berformat ...",
        [
            "INI",
            "CSV",
            "XML",
            "LDIF",
            "YAML",
        ],
        4,
        "ldapadd lazim membaca data entri dari file LDIF yang berisi pasangan atribut dan nilainya.",
    ),
    q(
        "Ketika user LDAP dihapus, langkah lanjutan yang paling masuk akal adalah ...",
        [
            "memastikan akun terkait di layanan turunan tidak tertinggal",
            "menghapus semua record A zone",
            "mematikan DHCP server",
            "mengganti FQDN seluruh host",
            "menonaktifkan HAProxy frontend",
        ],
        1,
        "Akun yang dihapus dari direktori pusat bisa masih tercatat di layanan lain, sehingga perlu dilakukan pengecekan lanjutan.",
    ),
    q(
        "Pada integrasi mail authentication dengan PAM dan LDAP, LDAP menyediakan ...",
        [
            "directory identity dan data autentikasi terpusat",
            "kapasitas penyimpanan mailbox fisik",
            "pemetaan DHCP reservation",
            "sertifikat TLS publik dari CA eksternal",
            "fungsi load balancing web",
        ],
        1,
        "LDAP menjadi sumber identitas dan autentikasi, sedangkan PAM memanfaatkan data itu untuk proses login layanan.",
    ),
    q(
        "Jika sebuah layanan membutuhkan reverse lookup yang benar, record DNS yang wajib tersedia adalah ...",
        [
            "AAAA",
            "NS",
            "SOA",
            "PTR",
            "TXT",
        ],
        4,
        "Reverse lookup bekerja dengan record PTR yang diletakkan pada reverse zone terkait.",
    ),
    q(
        "Dalam skenario sinkronisasi LDAP ke Samba, tujuan utamanya adalah ...",
        [
            "menyamakan identitas user agar dapat digunakan untuk autentikasi SMB",
            "mengubah IMAP menjadi POP3",
            "menghapus kebutuhan CA internal",
            "membuat gateway default otomatis",
            "menambah subnet baru secara dinamis",
        ],
        1,
        "Sinkronisasi tersebut menjaga agar akun terpusat tetap dapat dipakai saat mengakses layanan file sharing Samba.",
    ),
    q(
        "Port 25 pada Postfix umumnya dipakai untuk ...",
        [
            "SMTP antar server atau penerimaan email standar",
            "IMAP SSL client",
            "LDAP bind terenkripsi",
            "HTTPS load balancer",
            "Dovecot mailbox local delivery",
        ],
        1,
        "Port 25 lazim dipakai untuk SMTP standar, terutama komunikasi antarmail server.",
    ),
    q(
        "Jika sebuah client mail mengirim pesan melalui submission Postfix, alasan memakai port 587 adalah ...",
        [
            "karena port ini khusus untuk client yang diautentikasi",
            "karena port 587 adalah port DNS resmi",
            "karena port 587 hanya untuk reverse zone",
            "karena port ini menggantikan IMAPS",
            "karena port 587 digunakan oleh Samba public share",
        ],
        1,
        "Port 587 ditujukan bagi mail client yang mengirim email setelah melakukan autentikasi ke server submission.",
    ),
    q(
        "Bila sebuah private share tetap dapat dibuka semua orang, area pertama yang perlu dicek adalah ...",
        [
            "permission filesystem dan aturan akses share",
            "ukuran file ISO Debian",
            "timezone Linux server",
            "jumlah backend HAProxy",
            "serial number SOA record",
        ],
        1,
        "Masalah akses pada private share paling sering berasal dari kombinasi izin filesystem dan konfigurasi share Samba.",
    ),
    q(
        "Saat membangun topologi virtual, penomoran IP yang rapi penting karena ...",
        [
            "memudahkan routing, dokumentasi, dan troubleshooting",
            "menghapus kebutuhan DNS",
            "membuat sertifikat otomatis valid",
            "mengurangi ukuran disk VM",
            "membatasi user LDAP",
        ],
        1,
        "Perencanaan alamat yang rapi memudahkan pemetaan antarsegmen dan pemecahan masalah saat konfigurasi berjalan.",
    ),
]

QUESTIONS = QUESTIONS[:70]


def get_rows(root: ET.Element) -> list[ET.Element]:
    table = root.find(".//w:tbl", NS)
    if table is None:
        raise RuntimeError("Table not found in template document.")
    return table.findall("w:tr", NS)


def set_cell_text(cell: ET.Element, text: str) -> None:
    text_nodes = cell.findall(".//w:t", NS)
    if not text_nodes:
        return
    text_nodes[0].text = text
    for node in text_nodes[1:]:
        node.text = ""


def replace_question_blocks(root: ET.Element) -> None:
    rows = get_rows(root)
    expected_blocks = 70
    if len(QUESTIONS) != expected_blocks:
        raise RuntimeError(f"Expected {expected_blocks} questions, got {len(QUESTIONS)}.")

    start_row = 15
    block_size = 11
    field_order = [
        ("JENIS_SOAL", "multiple_choice"),
        ("SOAL", None),
        ("PILIHAN_1", None),
        ("PILIHAN_2", None),
        ("PILIHAN_3", None),
        ("PILIHAN_4", None),
        ("PILIHAN_5", None),
        ("JAWABAN", None),
        ("POIN", None),
        ("PEMBAHASAN", None),
    ]

    for idx, item in enumerate(QUESTIONS):
        base = start_row + (idx * block_size)
        separator_cells = rows[base].findall("w:tc", NS)
        if len(separator_cells) >= 2:
            set_cell_text(separator_cells[1], "---")

        values = {
            "SOAL": item["question"],
            "PILIHAN_1": item["options"][0],
            "PILIHAN_2": item["options"][1],
            "PILIHAN_3": item["options"][2],
            "PILIHAN_4": item["options"][3],
            "PILIHAN_5": item["options"][4],
            "JAWABAN": str(item["answer"]),
            "POIN": str(item["points"]),
            "PEMBAHASAN": item["explanation"],
        }
        for offset, (field_name, literal_value) in enumerate(field_order, start=1):
            row = rows[base + offset]
            cells = row.findall("w:tc", NS)
            if len(cells) < 2:
                raise RuntimeError(f"Unexpected row structure for question {idx + 1}, field {field_name}.")
            set_cell_text(cells[0], field_name)
            if literal_value is not None:
                set_cell_text(cells[1], literal_value)
            else:
                set_cell_text(cells[1], str(values[field_name]))


def write_docx() -> None:
    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
    with zipfile.ZipFile(TEMPLATE_PATH, "r") as zin:
        document_xml = zin.read("word/document.xml")
        root = ET.fromstring(document_xml)
        replace_question_blocks(root)
        new_document_xml = ET.tostring(root, encoding="utf-8", xml_declaration=True)

        with zipfile.ZipFile(OUTPUT_DOCX, "w") as zout:
            for info in zin.infolist():
                data = zin.read(info.filename)
                if info.filename == "word/document.xml":
                    data = new_document_xml
                zout.writestr(info, data)


def write_answer_key() -> None:
    lines = [
        "Kunci Jawaban - SOAL ITNSA 2024 70 Pilihan Ganda",
        f"Sumber topik playlist: {PLAYLIST_URL}",
        "",
    ]
    for idx, item in enumerate(QUESTIONS, start=1):
        lines.append(f"{idx:02d}. {item['answer']}")
    OUTPUT_KEY.write_text("\n".join(lines) + "\n", encoding="utf-8")


def write_metadata() -> None:
    payload = {
        "playlist_url": PLAYLIST_URL,
        "playlist_topics": PLAYLIST_TOPICS,
        "question_count": len(QUESTIONS),
        "questions": QUESTIONS,
    }
    OUTPUT_META.write_text(json.dumps(payload, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")


def main() -> None:
    if not TEMPLATE_PATH.exists():
        raise FileNotFoundError(f"Template not found: {TEMPLATE_PATH}")
    if len(PLAYLIST_TOPICS) != 30:
        raise RuntimeError("Expected 30 playlist topics.")
    write_docx()
    write_answer_key()
    write_metadata()
    print(f"Generated: {OUTPUT_DOCX}")
    print(f"Answer key: {OUTPUT_KEY}")
    print(f"Metadata: {OUTPUT_META}")


if __name__ == "__main__":
    main()
