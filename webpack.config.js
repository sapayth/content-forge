const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');

module.exports = (env = {}) => {
  // Default entry/output
  // let entry = './src/pages-posts-app.jsx';
  // let outputFilename = 'admin-app.js';
  const entry = {
    pagesPosts: './src/js/pages-posts.jsx',
    users: './src/js/users.jsx',
    taxonomies: './src/js/taxonomies.jsx',
    comments: './src/js/comments.jsx',
  }

  // Allow custom entry/output via env
  if (env.entry && env.output) {
    entry = path.resolve(__dirname, 'src', env.entry);
  }

  return {
    mode: 'production',
    entry,
    output: {
      path: path.resolve(__dirname, 'assets/js'),
      filename: '[name].js',
      clean: false,
    },
    resolve: {
      extensions: ['.js', '.jsx'],
    },
    module: {
      rules: [
        {
          test: /\.(js|jsx)$/,
          exclude: /node_modules/,
          use: {
            loader: 'babel-loader',
            options: {
              presets: [
                '@babel/preset-env',
                '@babel/preset-react',
              ],
            },
          },
        },
        {
          test: /\.css$/i,
          use: [MiniCssExtractPlugin.loader, 'css-loader', 'postcss-loader'],
        },
      ],
    },
    plugins: [
      new MiniCssExtractPlugin({
        filename: '../css/[name].css', // Output CSS to assets/css
      }),
    ],
  };
}; 