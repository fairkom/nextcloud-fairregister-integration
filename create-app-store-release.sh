#!/bin/bash
set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${GREEN}🚀 Creating Nextcloud App Store Release...${NC}"

# Check if we're in the right directory
if [ ! -f "fairregister/appinfo/info.xml" ]; then
    echo -e "${RED}❌ Error: Please run this script from the root of the nextcloud-fairregister-integration repository${NC}"
    echo -e "${YELLOW}Expected structure: fairregister/appinfo/info.xml${NC}"
    exit 1
fi

# Extract version from info.xml
VERSION=$(grep -o '<version>[^<]*</version>' fairregister/appinfo/info.xml | sed 's/<[^>]*>//g')
echo -e "${BLUE}📌 Version detected: ${VERSION}${NC}"

ARCHIVE_NAME="fairregister_v${VERSION}.tar.gz"
SIGNATURE_NAME="fairregister_v${VERSION}.sig"

# Check if release already exists
if [ -f "releases/${ARCHIVE_NAME}" ]; then
    echo -e "${RED}❌ Error: Release v${VERSION} already exists in releases/${NC}"
    echo -e "${YELLOW}   Files found: releases/${ARCHIVE_NAME}${NC}"
    echo -e "${YELLOW}   Please bump the version in fairregister/appinfo/info.xml first${NC}"
    exit 1
fi

echo -e "${BLUE}📂 Current directory: $(pwd)${NC}"

# Build the fairregister app for PRODUCTION
echo -e "${YELLOW}🔧 Building fairregister app for PRODUCTION (App Store)...${NC}"
cd fairregister

# Remove existing vendor to ensure clean state
echo -e "${YELLOW}   Cleaning old vendor directory...${NC}"
rm -rf vendor/

# Install PRODUCTION dependencies only (no dev dependencies)
echo -e "${YELLOW}   Installing PHP dependencies (production only)...${NC}"
composer install --no-dev --optimize-autoloader

echo -e "${YELLOW}   Installing Node.js dependencies...${NC}"
npm install

echo -e "${YELLOW}   Building frontend assets...${NC}"
npm run build

cd ..

echo -e "${GREEN}✅ Production build completed!${NC}"

# Clean macOS artifacts
echo -e "${YELLOW}🧹 Cleaning macOS artifacts...${NC}"
find fairregister -name "._*" -type f -delete 2>/dev/null || true
find fairregister -name ".DS_Store" -type f -delete 2>/dev/null || true

# Create tar.gz archive for App Store
echo -e "${YELLOW}📦 Creating App Store package (tar.gz)...${NC}"

COPYFILE_DISABLE=1 tar --exclude='fairregister/node_modules' \
  --exclude='fairregister/.git' \
  --exclude='fairregister/tests' \
  --exclude='fairregister/.editorconfig' \
  --exclude='fairregister/.gitignore' \
  --exclude='fairregister/phpunit.xml*' \
  --exclude='fairregister/psalm*' \
  --exclude='fairregister/phpstan*' \
  -czf "${ARCHIVE_NAME}" fairregister

echo -e "${GREEN}✅ Archive created: ${ARCHIVE_NAME}${NC}"

# Get package size
PACKAGE_SIZE=$(du -h "${ARCHIVE_NAME}" | cut -f1)
echo -e "${BLUE}📊 Package size: ${PACKAGE_SIZE}${NC}"

# Check if certificates exist
if [ ! -f ~/.nextcloud/certificates/fairregister.key ]; then
    echo -e "${RED}❌ Error: Certificate not found at ~/.nextcloud/certificates/fairregister.key${NC}"
    echo -e "${YELLOW}   Please create certificates first (see nextcloud-app-submission.md)${NC}"
    exit 1
fi

# Create signature
echo -e "${YELLOW}🔐 Creating signature...${NC}"
openssl dgst -sha512 -sign ~/.nextcloud/certificates/fairregister.key "${ARCHIVE_NAME}" | openssl base64 > "${SIGNATURE_NAME}"

echo -e "${GREEN}✅ Signature created: ${SIGNATURE_NAME}${NC}"

# Show signature content
echo -e "${BLUE}📋 Signature content:${NC}"
cat "${SIGNATURE_NAME}"
echo ""

# Create releases directory if it doesn't exist
mkdir -p releases

# Move files to releases directory
echo -e "${YELLOW}📁 Moving files to releases/...${NC}"
mv "${ARCHIVE_NAME}" releases/
mv "${SIGNATURE_NAME}" releases/

echo -e "${GREEN}✅ Files moved to releases/${NC}"

# Display final summary
echo ""
echo -e "${GREEN}🎉 App Store Release v${VERSION} created successfully!${NC}"
echo ""
echo -e "${BLUE}📋 Release Summary:${NC}"
echo -e "   Version: ${YELLOW}${VERSION}${NC}"
echo -e "   Archive: ${YELLOW}releases/${ARCHIVE_NAME}${NC} (${PACKAGE_SIZE})"
echo -e "   Signature: ${YELLOW}releases/${SIGNATURE_NAME}${NC}"
echo ""
echo -e "${BLUE}📤 Next steps:${NC}"
echo -e "   1. Upload to GitHub Release:"
echo -e "      ${YELLOW}https://github.com/fairkom/nextcloud-fairregister-integration/releases/new${NC}"
echo -e "      Tag: ${YELLOW}v${VERSION}${NC}"
echo -e "      Upload file: ${YELLOW}releases/${ARCHIVE_NAME}${NC}"
echo ""
echo -e "   2. Submit to Nextcloud App Store:"
echo -e "      Login: ${YELLOW}https://apps.nextcloud.com/${NC}"
echo -e "      Your App: ${YELLOW}https://apps.nextcloud.com/apps/fairregister${NC}"
echo -e "      Click 'Upload new release'"
echo -e "      Download link: GitHub release URL"
echo -e "      Signature: Content of ${YELLOW}releases/${SIGNATURE_NAME}${NC}"
echo ""
echo -e "${GREEN}✨ Done!${NC}"
