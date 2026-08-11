import Alpine from 'alpinejs';

import assetForm from './admin/asset-form';
import copyButton from './admin/copy-button';
import invitationForm from './admin/invitation-form';
import palettePicker from './admin/palette-picker';
import settingsForm from './admin/settings-form';
import templatePreview from './admin/template-preview';
import designPreview from './public/design-preview';

// Components are registered here and referenced by name from Blade, rather
// than written inline in an x-data="..." attribute. A literal double quote
// inside such an attribute closes it early and dumps the rest of the script
// onto the page as visible text, with no error to notice. Keeping the code in
// .js files removes that failure entirely — and makes it lintable, diffable
// and syntax-highlighted besides.
//
// Blade passes server values as a single escaped object:
//     x-data="invitationForm(@js([...]))"
Alpine.data('assetForm', assetForm);
Alpine.data('copyButton', copyButton);
Alpine.data('designPreview', designPreview);
Alpine.data('invitationForm', invitationForm);
Alpine.data('palettePicker', palettePicker);
Alpine.data('settingsForm', settingsForm);
Alpine.data('templatePreview', templatePreview);

window.Alpine = Alpine;
Alpine.start();
