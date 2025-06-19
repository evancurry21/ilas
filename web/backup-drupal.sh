#!/bin/bash

# Drupal Backup Script
# Run this script from your Drupal root directory

# Configuration
BACKUP_DIR="./backups"
DATE=$(date +%Y%m%d_%H%M%S)
SITE_NAME="ilas"

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo "Starting Drupal backup process..."

# Create backup directory if it doesn't exist
mkdir -p $BACKUP_DIR

# 1. Database Backup
echo -e "${GREEN}Backing up database...${NC}"

# Check if we're using DDEV
if [ -d "../.ddev" ] && command -v ddev &> /dev/null; then
    echo "DDEV environment detected. Using DDEV drush..."
    cd ..
    ddev drush sql-dump --gzip > "web/$BACKUP_DIR/${SITE_NAME}_db_${DATE}.sql.gz"
    if [ $? -eq 0 ]; then
        echo "Database backed up to: web/$BACKUP_DIR/${SITE_NAME}_db_${DATE}.sql.gz"
    else
        echo -e "${RED}Database backup failed. Check DDEV is running: ddev start${NC}"
    fi
    cd web
# Check for drush in vendor/bin
elif [ -x "../vendor/bin/drush" ]; then
    ../vendor/bin/drush sql-dump --gzip > "$BACKUP_DIR/${SITE_NAME}_db_${DATE}.sql.gz"
    if [ $? -eq 0 ]; then
        echo "Database backed up to: $BACKUP_DIR/${SITE_NAME}_db_${DATE}.sql.gz"
    else
        echo -e "${RED}Database backup failed. Check your database configuration.${NC}"
    fi
elif command -v drush &> /dev/null; then
    drush sql-dump --gzip > "$BACKUP_DIR/${SITE_NAME}_db_${DATE}.sql.gz"
    if [ $? -eq 0 ]; then
        echo "Database backed up to: $BACKUP_DIR/${SITE_NAME}_db_${DATE}.sql.gz"
    else
        echo -e "${RED}Database backup failed. Check your database configuration.${NC}"
    fi
else
    echo -e "${RED}Drush not found. Please install Drush or use manual database export.${NC}"
fi

# 2. Files Backup
echo -e "${GREEN}Backing up files...${NC}"

# Create files archive
tar -czf "$BACKUP_DIR/${SITE_NAME}_files_${DATE}.tar.gz" \
    sites/default/files \
    sites/default/settings.php \
    sites/default/services.yml \
    2>/dev/null

echo "Files backed up to: $BACKUP_DIR/${SITE_NAME}_files_${DATE}.tar.gz"

# 3. Custom Code Backup
echo -e "${GREEN}Backing up custom code...${NC}"

tar -czf "$BACKUP_DIR/${SITE_NAME}_custom_${DATE}.tar.gz" \
    modules/custom \
    themes/custom \
    2>/dev/null

echo "Custom code backed up to: $BACKUP_DIR/${SITE_NAME}_custom_${DATE}.tar.gz"

# 4. Full Site Backup (optional - this will be large)
echo -e "${GREEN}Creating full site archive...${NC}"
echo "This may take several minutes..."

tar -czf "$BACKUP_DIR/${SITE_NAME}_full_${DATE}.tar.gz" \
    --exclude='./backups' \
    --exclude='./node_modules' \
    --exclude='./.git' \
    --exclude='./sites/default/files/styles' \
    --exclude='./sites/default/files/css' \
    --exclude='./sites/default/files/js' \
    .

echo "Full site backed up to: $BACKUP_DIR/${SITE_NAME}_full_${DATE}.tar.gz"

# 5. Create backup manifest
echo -e "${GREEN}Creating backup manifest...${NC}"

cat > "$BACKUP_DIR/manifest_${DATE}.txt" << EOF
Drupal Backup Manifest
Date: $(date)
Site: ${SITE_NAME}

Files included:
- Database: ${SITE_NAME}_db_${DATE}.sql.gz
- Files: ${SITE_NAME}_files_${DATE}.tar.gz
- Custom Code: ${SITE_NAME}_custom_${DATE}.tar.gz
- Full Site: ${SITE_NAME}_full_${DATE}.tar.gz

To restore:
1. Database: gunzip < ${SITE_NAME}_db_${DATE}.sql.gz | drush sqlc
2. Files: tar -xzf ${SITE_NAME}_files_${DATE}.tar.gz
3. Custom Code: tar -xzf ${SITE_NAME}_custom_${DATE}.tar.gz
4. Clear caches: drush cr
EOF

echo -e "${GREEN}Backup complete!${NC}"
echo "All backups saved to: $BACKUP_DIR"
echo "Manifest file: $BACKUP_DIR/manifest_${DATE}.txt"

# 6. Copy to Windows Desktop folder
WINDOWS_BACKUP="/mnt/c/Users/Evan/Desktop/ILAS Site Backup"
if [ -d "$WINDOWS_BACKUP" ]; then
    echo -e "${GREEN}Copying backups to Windows Desktop...${NC}"
    # Create a dated subfolder
    WINDOWS_BACKUP_DATED="$WINDOWS_BACKUP/backup_${DATE}"
    mkdir -p "$WINDOWS_BACKUP_DATED"
    
    # Copy all backup files
    cp "$BACKUP_DIR"/*_${DATE}.* "$WINDOWS_BACKUP_DATED/"
    
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
#     rclone copy "$BACKUP_DIR"/*_${DATE}.* "gdrive:${GDRIVE_FOLDER}/" --progress
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

# Optional: Delete old backups (keep last 5)
# find $BACKUP_DIR -name "${SITE_NAME}_*.tar.gz" -mtime +30 -delete
# find "$WINDOWS_BACKUP" -maxdepth 1 -name "backup_*" -type d -mtime +30 -exec rm -rf {} \;