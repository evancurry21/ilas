#!/bin/bash

# Drupal Backup Script - Updated Version
# Run this script from your Drupal root directory

# Configuration
BACKUP_DIR="$HOME/drupal-backups/ilas"  # Store outside web root
DATE=$(date +%Y%m%d_%H%M%S)
SITE_NAME="ilas"

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "Starting Drupal backup process..."

# Create backup directory with timestamp
BACKUP_SUBDIR="$BACKUP_DIR/backup_${DATE}"
mkdir -p "$BACKUP_SUBDIR"

# 1. Main Database Backup
echo -e "${GREEN}Backing up main database...${NC}"

# Check if we're using DDEV
if [ -d "../.ddev" ] && command -v ddev &> /dev/null; then
    echo "DDEV environment detected. Using DDEV drush..."
    cd ..
    ddev drush sql-dump --gzip > "$BACKUP_SUBDIR/${SITE_NAME}_db_${DATE}.sql.gz"
    if [ $? -eq 0 ]; then
        echo "Database backed up to: $BACKUP_SUBDIR/${SITE_NAME}_db_${DATE}.sql.gz"
    else
        echo -e "${RED}Database backup failed. Check DDEV is running: ddev start${NC}"
    fi
    
    # 1b. CiviCRM Database Backup
    echo -e "${GREEN}Backing up CiviCRM database...${NC}"
    ddev export-db --database=ilas_civicrm --file="$BACKUP_SUBDIR/${SITE_NAME}_civicrm_db_${DATE}.sql.gz"
    if [ $? -eq 0 ]; then
        echo "CiviCRM database backed up to: $BACKUP_SUBDIR/${SITE_NAME}_civicrm_db_${DATE}.sql.gz"
    else
        echo -e "${YELLOW}CiviCRM database backup failed (this is okay if CiviCRM is not installed)${NC}"
    fi
    cd web
else
    echo -e "${RED}Non-DDEV backup not implemented. Please use DDEV for backups.${NC}"
    exit 1
fi

# 2. Files Backup
echo -e "${GREEN}Backing up files...${NC}"

# Create files archive
tar -czf "$BACKUP_SUBDIR/${SITE_NAME}_files_${DATE}.tar.gz" \
    sites/default/files \
    sites/default/settings.php \
    sites/default/services.yml \
    sites/default/civicrm.settings.php \
    2>/dev/null

echo "Files backed up to: $BACKUP_SUBDIR/${SITE_NAME}_files_${DATE}.tar.gz"

# Check for private files directory
if [ -d "sites/default/private" ]; then
    echo -e "${GREEN}Backing up private files...${NC}"
    tar -czf "$BACKUP_SUBDIR/${SITE_NAME}_private_files_${DATE}.tar.gz" \
        sites/default/private \
        2>/dev/null
    echo "Private files backed up to: $BACKUP_SUBDIR/${SITE_NAME}_private_files_${DATE}.tar.gz"
fi

# 3. Custom Code Backup
echo -e "${GREEN}Backing up custom code...${NC}"

tar -czf "$BACKUP_SUBDIR/${SITE_NAME}_custom_${DATE}.tar.gz" \
    modules/custom \
    themes/custom \
    2>/dev/null

echo "Custom code backed up to: $BACKUP_SUBDIR/${SITE_NAME}_custom_${DATE}.tar.gz"

# 4. Full Site Backup (optional - this will be large)
echo -e "${GREEN}Creating full site archive...${NC}"
echo "This may take several minutes..."

cd ..
tar -czf "$BACKUP_SUBDIR/${SITE_NAME}_full_${DATE}.tar.gz" \
    --exclude='web/backups' \
    --exclude='node_modules' \
    --exclude='.git' \
    --exclude='vendor' \
    --exclude='web/sites/default/files/styles' \
    --exclude='web/sites/default/files/css' \
    --exclude='web/sites/default/files/js' \
    web

echo "Full site backed up to: $BACKUP_SUBDIR/${SITE_NAME}_full_${DATE}.tar.gz"
cd web

# 5. Create backup manifest
echo -e "${GREEN}Creating backup manifest...${NC}"

cat > "$BACKUP_SUBDIR/manifest_${DATE}.txt" << EOF
Drupal Backup Manifest
Date: $(date)
Site: ${SITE_NAME}
Backup Location: $BACKUP_SUBDIR

Files included:
- Main Database: ${SITE_NAME}_db_${DATE}.sql.gz
- CiviCRM Database: ${SITE_NAME}_civicrm_db_${DATE}.sql.gz
- Files: ${SITE_NAME}_files_${DATE}.tar.gz
- Private Files: ${SITE_NAME}_private_files_${DATE}.tar.gz (if exists)
- Custom Code: ${SITE_NAME}_custom_${DATE}.tar.gz
- Full Site: ${SITE_NAME}_full_${DATE}.tar.gz

To restore:
1. Main Database: gunzip < ${SITE_NAME}_db_${DATE}.sql.gz | ddev drush sqlc
2. CiviCRM Database: gunzip < ${SITE_NAME}_civicrm_db_${DATE}.sql.gz | ddev mysql -d ilas_civicrm
3. Files: tar -xzf ${SITE_NAME}_files_${DATE}.tar.gz -C /path/to/drupal/web/
4. Custom Code: tar -xzf ${SITE_NAME}_custom_${DATE}.tar.gz -C /path/to/drupal/web/
5. Clear caches: ddev drush cr
6. Set file permissions: chmod 644 sites/default/settings.php sites/default/civicrm.settings.php
EOF

echo -e "${GREEN}Backup complete!${NC}"
echo "All backups saved to: $BACKUP_SUBDIR"
echo "Manifest file: $BACKUP_SUBDIR/manifest_${DATE}.txt"

# 6. Copy to Windows Desktop folder
WINDOWS_BACKUP="/mnt/c/Users/Evan/Desktop/ILAS Site Backup"
if [ -d "$WINDOWS_BACKUP" ]; then
    echo -e "${GREEN}Copying backups to Windows Desktop...${NC}"
    # Create a dated subfolder
    WINDOWS_BACKUP_DATED="$WINDOWS_BACKUP/backup_${DATE}"
    mkdir -p "$WINDOWS_BACKUP_DATED"
    
    # Copy all backup files
    cp -r "$BACKUP_SUBDIR"/* "$WINDOWS_BACKUP_DATED/"
    
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓ Backups copied to: C:\\Users\\Evan\\Desktop\\ILAS Site Backup\\backup_${DATE}${NC}"
    else
        echo -e "${RED}Failed to copy to Windows folder${NC}"
    fi
else
    echo -e "${RED}Windows backup folder not found: $WINDOWS_BACKUP${NC}"
fi

# 7. Google Drive backup (uncomment after setting up rclone)
# if command -v rclone &> /dev/null; then
#     echo -e "${GREEN}Uploading to Google Drive...${NC}"
#     # Create dated folder on Google Drive
#     GDRIVE_FOLDER="ILAS Site Backups/backup_${DATE}"
#     rclone mkdir "gdrive:${GDRIVE_FOLDER}"
#     
#     # Upload all backup files
#     rclone copy "$BACKUP_SUBDIR"/* "gdrive:${GDRIVE_FOLDER}/" --progress
#     
#     if [ $? -eq 0 ]; then
#         echo -e "${GREEN}✓ Backups uploaded to Google Drive: ${GDRIVE_FOLDER}${NC}"
#     else
#         echo -e "${RED}Failed to upload to Google Drive${NC}"
#     fi
# else
#     echo -e "${YELLOW}rclone not installed - skipping Google Drive backup${NC}"
#     echo "To enable Google Drive backup, see: GOOGLE_DRIVE_BACKUP_SETUP.md"
# fi

# Cleanup old backups (keep last 5)
echo -e "${GREEN}Cleaning up old backups...${NC}"
# Keep only the 5 most recent backup directories
cd "$BACKUP_DIR"
ls -dt backup_* | tail -n +6 | xargs -r rm -rf

# Clean Windows backups too
if [ -d "$WINDOWS_BACKUP" ]; then
    cd "$WINDOWS_BACKUP"
    ls -dt backup_* | tail -n +6 | xargs -r rm -rf
fi

echo -e "${GREEN}✓ Backup process complete!${NC}"