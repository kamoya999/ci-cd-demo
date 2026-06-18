# =====================================================================
#  ローカル開発用 Web コンテナ（CentOS 6 系 + Apache 2.2 + PHP 5.3）
#
#  本番が PHP 5.3 のため、ローカルも同じ 5.3 に合わせて差異を早期発見する。
#  ※ CentOS 6.5 は EOL のため、Docker Hub で安定して取得できる 6.6 を使用。
#     PHP は CentOS 6.x のいずれでも base に 5.3.3 が入る（OS マイナーは表示上の差）。
#
#  ★必須★ WSL2/Docker Desktop で本イメージを使うには、ホストの WSL2 カーネルに
#     vsyscall=emulate を設定してください（未設定だと yum が SIGSEGV → ビルドが
#     exit 139 で失敗します）。手順:
#       1) C:\Users\<ユーザー名>\.wslconfig に以下を記述
#            [wsl2]
#            kernelCommandLine = vsyscall=emulate
#       2) PowerShell: wsl --shutdown → Docker Desktop 再起動
#     WSL2 を触りたくない場合は Dockerfile.php56（公式 php:5.6-apache）を使う。
# =====================================================================
FROM centos:6.6

# CentOS 6 は EOL でミラー(mirror.centos.org)が停止済み。
# アーカイブの vault.centos.org を参照するよう yum を再設定する。
# （Windows ノート PC = x86_64 前提で basearch を固定）
RUN rm -f /etc/yum.repos.d/*.repo && \
    printf '%s\n' \
      '[base]' \
      'name=CentOS-6 Base (vault)' \
      'baseurl=https://vault.centos.org/6.6/os/x86_64/' \
      'gpgcheck=0' \
      'enabled=1' \
      '' \
      '[updates]' \
      'name=CentOS-6 Updates (vault)' \
      'baseurl=https://vault.centos.org/6.6/updates/x86_64/' \
      'gpgcheck=0' \
      'enabled=1' \
      > /etc/yum.repos.d/CentOS-Vault.repo && \
    yum -y install httpd php php-mysql php-mbstring php-gd && \
    yum clean all

# Apache 設定（DocumentRoot / .htaccess 許可）
COPY httpd-vhost.conf /etc/httpd/conf.d/vhost.conf

# ビルドログでバージョンを確認できるようにする
RUN php -v && httpd -v

EXPOSE 80

# Apache をフォアグラウンドで起動（コンテナのメインプロセス）
CMD ["/usr/sbin/httpd", "-D", "FOREGROUND"]
