# 🎓 AMC Web Platform

Interface web moderne pour créer et gérer des QCM avec Auto Multiple Choice.

## ✨ Fonctionnalités testées et fonctionnelles

- 🔐 **Authentification complète** - Inscription, connexion, sessions
- 📝 **Gestion de projets** - Création, sauvegarde persistante
- 📊 **Dashboard personnel** - Statistiques en temps réel
- 🎯 **API REST complète** - Backend PHP robuste
- 📱 **Interface responsive** - Design moderne et intuitif

## 🚀 Installation rapide

```bash
# 1. Cloner le projet
git clone https://github.com/madmohsii/amc-web-platform.git
cd amc-web-platform

# 2. Installer les dépendances (Ubuntu/Debian)
sudo apt update
sudo apt install apache2 php php-cli php-sqlite3 auto-multiple-choice

# 3. Configurer Apache
sudo cp config/amc.conf /etc/apache2/sites-available/
sudo a2ensite amc.conf
sudo systemctl reload apache2

# 4. Définir les permissions
sudo chown -R $USER:www-data .
chmod -R 755 .
chmod -R 775 data/
