# Credit Key Magento 1 Module

## Installation

There are 2 ways to install

### 1. Install using composer - recommended way
From your root Magento directory, run the following command:

```
% composer require creditkey/magento1-b2bgateway
```

Module is using `magento-hackathon/magento-composer-installer` extension to intsall self into magento code pool folder.

After the installation the module will be avaialable in the admin under payment methods section.
Since module uses symlink then magento has to be able to follow symlinks. 
To do that following config should be enabled: dev/template/allow_symlink 

## Configuration

From the Magento admin, navigate to ```System > Configuration > Sales > Payment Methods``` and scroll down to the ```Credit Key (Gateway)``` section.

The `Marketing Content on Product Pages` section allows you to enable the Credit Key marketing content to be displayed on the selected product detail pages. You can enable/disable this feature globally, select the specific categories to allow the content to be displayed on the products belonging to said categories, and select the style of the displayed content.

## Customization

To move the location of the marketing display on the product details page you will need to modify the file `creditkey/marketing.xml` from your active theme. 
This will most likely be located at `{magento_root}/theme/design/frontend/{YourCompany}/{theme-name}/layout/creditkey/marketing.xml`.

To see the available containers you can use reference Magento's primary `catalog.xml` file, located at `app/design/frontend/rwd/default/layout/catalog.xml`.
