(function (fwe, _, itemData) {
	fwe.one('fw-builder:' + 'page-builder' + ':register-items', function (builder) {
		var PageBuilderContactFormItem,
			PageBuilderContactFormItemView;

		PageBuilderContactFormItemView = builder.classes.ItemView.extend({
			initialize: function (options) {
				this.defaultInitialize();

				this.templateData = options.templateData;

				if (options.modalOptions) {
					this.modal = new fw.OptionsModal({
						title: 'Contact Form', // TODO: make translatable
						options: options.modalOptions,
						values: this.model.get('atts'),
						size: options.modalSize
					});

					this.listenTo(this.modal, 'change:values', function (modal, values) {
						this.model.set('atts', values);
					});
				}
			},
			template: _.template(
				'<div class="pb-item-type-contact-form pb-item-type-simple pb-item <% if (hasOptions) { print(' + '"has-options"' + ')} %>">' +
					'<div class="panel fw-row">' +
						'<%- title %>' +
						'<div class="controls">' +
							'<% if (!isMailer) { %>' +
							'<i class="dashicons dashicons-info contact-form-item-mailer" data-hover-tip="<%- configureMailer %>"></i>' +
							'<%  } %>' +
							'<% if (hasOptions) { %>' +
							'<i class="dashicons dashicons-welcome-write-blog edit-options"></i>' +
							'<%  } %>' +
							'<i class="dashicons dashicons-admin-page contact-form-item-clone"></i>' +
							'<i class="dashicons dashicons-no contact-form-item-delete"></i>' +
						'</div>' +
					'</div>' +
				'</div>'
			),
			render: function () {
				this.defaultRender(this.templateData);
			},
			events: {
				'click .contact-form-item-mailer': 'configureMailer',
				'click .edit-options': 'editOptions',
				'click .contact-form-item-clone': 'cloneItem',
				'click .contact-form-item-delete': 'removeItem'
			},
			editOptions: function (e) {
				e.stopPropagation();
				if (!this.modal) {
					return;
				}
				this.modal.open();
			},
			configureMailer: function (e) {
				this.editOptions(e);

				fwe.on('fw:options:init', function (data) {
					data.$elements.find('.fw-options-tabs-wrapper').find('a[href="#fw-options-tab-settings"]').trigger('click');
					data.$elements.find('.fw-options-tabs-wrapper').find('#fw-options-tab-settings').find('a[href="#fw-options-tab-mailer-options"]').trigger('click');
				});
			},
			cloneItem: function () {
				var index = this.model.collection.indexOf(this.model),
					attributes = this.model.toJSON(),
					_items = attributes['_items'],
					clonedColumn;

				delete attributes['_items'];

				clonedColumn = new PageBuilderContactFormItem(attributes);
				this.model.collection.add(clonedColumn, {at: index + 1});
				clonedColumn.get('_items').reset(_items);
			},
			removeItem: function () {
				this.remove();
				this.model.collection.remove(this.model);
			}
		});

		PageBuilderContactFormItem = builder.classes.Item.extend({
			defaults: {
				type: 'contact-form'
			},
			restrictedTypes: itemData.restrictedTypes,
			initialize: function (atts, opts) {

				this.view = new PageBuilderContactFormItemView({
					id: 'page-builder-item-' + this.cid,
					model: this,
					modalOptions: itemData.options,
					modalSize: itemData.popup_size,
					templateData: {
						title: itemData.title,
						isMailer : itemData.mailer,
						configureMailer : itemData.configureMailer,
						hasOptions: !!itemData.options
					}
				});

				this.defaultInitialize();
			},
			allowIncomingType: function (type) {
				return _.indexOf(this.restrictedTypes, type) === -1;
			}
		});

		builder.registerItemClass(PageBuilderContactFormItem);
	});
})(fwEvents, _, page_builder_item_type_contact_form_data);
