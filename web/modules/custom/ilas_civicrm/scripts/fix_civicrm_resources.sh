#!/bin/bash

# Script to fix CiviCRM resource loading issues
# This creates a symlink from libraries/civicrm to the actual CiviCRM resources

DRUPAL_ROOT="/home/evancurry/ilas/web"
LIBRARIES_DIR="$DRUPAL_ROOT/libraries"
CIVICRM_CORE="$DRUPAL_ROOT/../vendor/civicrm/civicrm-core"

# Create libraries directory if it doesn't exist
if [ ! -d "$LIBRARIES_DIR" ]; then
    echo "Creating libraries directory..."
    mkdir -p "$LIBRARIES_DIR"
fi

# Remove existing civicrm directory/symlink if it exists
if [ -e "$LIBRARIES_DIR/civicrm" ]; then
    echo "Removing existing civicrm link/directory..."
    rm -rf "$LIBRARIES_DIR/civicrm"
fi

# Create symlink to CiviCRM core
echo "Creating symlink from $LIBRARIES_DIR/civicrm to $CIVICRM_CORE..."
ln -s "$CIVICRM_CORE" "$LIBRARIES_DIR/civicrm"

# Verify the symlink was created
if [ -L "$LIBRARIES_DIR/civicrm" ]; then
    echo "✓ Symlink created successfully"
    echo "  Target: $(readlink -f "$LIBRARIES_DIR/civicrm")"
    
    # Check if CSS files are accessible
    if [ -f "$LIBRARIES_DIR/civicrm/css/civicrm.css" ]; then
        echo "✓ civicrm.css is accessible"
    else
        echo "✗ civicrm.css is NOT accessible"
    fi
    
    if [ -f "$LIBRARIES_DIR/civicrm/css/crm-i.css" ]; then
        echo "✓ crm-i.css is accessible"
    else
        echo "✗ crm-i.css is NOT accessible"
    fi
else
    echo "✗ Failed to create symlink"
    exit 1
fi

echo ""
echo "Next steps:"
echo "1. Clear Drupal cache: drush cr"
echo "2. Test the site to see if CiviCRM CSS loads correctly"