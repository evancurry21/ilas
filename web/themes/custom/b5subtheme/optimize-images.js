const imagemin = require('imagemin').default || require('imagemin');
const imageminMozjpeg = require('imagemin-mozjpeg').default || require('imagemin-mozjpeg');
const imageminPngquant = require('imagemin-pngquant').default || require('imagemin-pngquant');
const imageminGifsicle = require('imagemin-gifsicle').default || require('imagemin-gifsicle');
const imageminSvgo = require('imagemin-svgo').default || require('imagemin-svgo');
const fs = require('fs');
const path = require('path');

async function optimizeImages() {
  console.log('Starting image optimization...');
  
  // Create optimized directory if it doesn't exist
  const outputDir = 'images/optimized';
  if (!fs.existsSync(outputDir)) {
    fs.mkdirSync(outputDir, { recursive: true });
  }

  try {
    const files = await imagemin(['images/**/*.{jpg,jpeg,png,gif,svg}'], {
      destination: outputDir,
      plugins: [
        imageminMozjpeg({
          quality: 85,
          progressive: true
        }),
        imageminPngquant({
          quality: [0.8, 0.9]
        }),
        imageminGifsicle({
          interlaced: true,
          optimizationLevel: 3
        }),
        imageminSvgo({
          plugins: [
            {
              name: 'preset-default',
              params: {
                overrides: {
                  removeViewBox: false,
                  cleanupIds: false
                }
              }
            }
          ]
        })
      ]
    });

    console.log(`Optimized ${files.length} images:`);
    files.forEach(file => {
      const original = file.sourcePath;
      const optimized = file.destinationPath;
      const originalSize = fs.statSync(original).size;
      const optimizedSize = fs.statSync(optimized).size;
      const savedPercent = ((originalSize - optimizedSize) / originalSize * 100).toFixed(1);
      console.log(`  ${path.basename(original)}: ${(originalSize/1024).toFixed(1)}KB → ${(optimizedSize/1024).toFixed(1)}KB (saved ${savedPercent}%)`);
    });

  } catch (error) {
    console.error('Error optimizing images:', error);
  }
}

// Run the optimization
optimizeImages();