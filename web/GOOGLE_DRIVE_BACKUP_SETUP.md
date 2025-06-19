# Google Drive Backup Setup Instructions

## Option 1: Using rclone (Recommended)

### Step 1: Install rclone
```bash
curl https://rclone.org/install.sh | sudo bash
```

### Step 2: Configure rclone for Google Drive
```bash
rclone config
```

Follow these steps in the config:
1. Type `n` for new remote
2. Name it `gdrive`
3. Choose number for "Google Drive" (usually 15)
4. Leave client_id and client_secret blank (press Enter)
5. Choose scope: `1` for full access
6. Leave root_folder_id blank
7. Leave service_account_file blank
8. Choose `n` for advanced config
9. Choose `y` for auto config
10. Login to Google in your browser when it opens
11. Choose `y` to confirm
12. Type `q` to quit config

### Step 3: Test the connection
```bash
rclone lsd gdrive:
```

### Step 4: Create backup folder on Google Drive
```bash
rclone mkdir gdrive:"ILAS Site Backups"
```

### Step 5: Update backup script
Add this to your backup script after the Windows backup section:

```bash
# Google Drive backup using rclone
if command -v rclone &> /dev/null; then
    echo -e "${GREEN}Uploading to Google Drive...${NC}"
    # Create dated folder on Google Drive
    GDRIVE_FOLDER="ILAS Site Backups/backup_${DATE}"
    rclone mkdir "gdrive:${GDRIVE_FOLDER}"
    
    # Upload all backup files
    rclone copy "$BACKUP_DIR"/*_${DATE}.* "gdrive:${GDRIVE_FOLDER}/" --progress
    
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓ Backups uploaded to Google Drive: ${GDRIVE_FOLDER}${NC}"
    else
        echo -e "${RED}Failed to upload to Google Drive${NC}"
    fi
else
    echo -e "${YELLOW}rclone not installed - skipping Google Drive backup${NC}"
fi
```

## Option 2: Manual Upload

1. Open File Explorer
2. Navigate to: `C:\Users\Evan\Desktop\ILAS Site Backup`
3. Find the latest backup folder (e.g., `backup_20250618_134133`)
4. Upload the entire folder to Google Drive manually

## Option 3: Using Google Drive Desktop App

1. Install Google Drive for Desktop from: https://www.google.com/drive/download/
2. During setup, choose to sync a folder
3. Add this folder: `C:\Users\Evan\Desktop\ILAS Site Backup`
4. Google Drive will automatically sync all backups

## Recommended Backup Strategy

1. **Automatic local backup** - The script saves to WSL filesystem
2. **Automatic Windows backup** - The script copies to your Desktop
3. **Automatic cloud backup** - Either:
   - Use rclone (automated in script)
   - Use Google Drive Desktop app (automatic sync)
   - Manual upload after each backup

## Testing Your Backups

Always test that you can restore from your backups:
1. Download a backup from Google Drive
2. Extract and verify the files
3. Test database restore in a development environment