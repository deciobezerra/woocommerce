/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import {
	Button,
	Modal,
	Icon,
	Flex,
	FlexBlock,
	FlexItem,
} from '@wordpress/components';
import { chevronUp, chevronDown, external } from '@wordpress/icons';
import classnames from 'classnames';

/**
 * Internal dependencies
 */
import { RecommendedChannelsList } from '~/marketing/components';
import { useRecommendedChannels } from '~/marketing/hooks';
import { useNewCampaignTypes } from './useNewCampaignTypes';
import './CreateNewCampaignModal.scss';

const isExternalURL = ( url: string ) =>
	new URL( url ).origin !== location.origin;

/**
 * Props for CreateNewCampaignModal, which is based on Modal.Props.
 *
 * Modal's title and children props are omitted because they are specified within the component
 * and not needed to be specified by the consumer.
 */
type CreateCampaignModalProps = Omit< Modal.Props, 'title' | 'children' >;

export const CreateNewCampaignModal = ( props: CreateCampaignModalProps ) => {
	const { className, ...restProps } = props;
	const [ collapsed, setCollapsed ] = useState( true );
	// TOOD: handle loading state.
	const { loading, data: newCampaignTypes } = useNewCampaignTypes();
	const { data: recommendedChannels } = useRecommendedChannels();

	return (
		<Modal
			className={ classnames(
				className,
				'woocommerce-marketing-create-campaign-modal'
			) }
			title={ __( 'Create a new campaign', 'woocommerce' ) }
			{ ...restProps }
		>
			<div className="woocommerce-marketing-new-campaigns">
				<div className="woocommerce-marketing-new-campaigns__question-label">
					{ __(
						'Where would you like to promote your products?',
						'woocommerce'
					) }
				</div>
				<div>
					{ newCampaignTypes.map( ( el ) => {
						return (
							<Flex
								key={ el.id }
								className="woocommerce-marketing-new-campaign-type"
								gap={ 4 }
							>
								<FlexItem>
									<img
										src={ el.icon }
										alt={ el.name }
										width="32"
										height="32"
									/>
								</FlexItem>
								<FlexBlock>
									<Flex direction="column" gap={ 1 }>
										<FlexItem className="woocommerce-marketing-new-campaign-type__name">
											{ el.name }
										</FlexItem>
										<FlexItem className="woocommerce-marketing-new-campaign-type__description">
											{ el.description }
										</FlexItem>
									</Flex>
								</FlexBlock>
								<FlexItem>
									<Button
										variant="secondary"
										href={ el.createUrl }
										target={
											isExternalURL( el.createUrl )
												? '_blank'
												: '_self'
										}
									>
										<Flex gap={ 1 }>
											<FlexItem>
												{ __(
													'Create',
													'woocommerce'
												) }
											</FlexItem>
											{ isExternalURL( el.createUrl ) && (
												<FlexItem>
													<Icon
														icon={ external }
														size={ 16 }
													/>
												</FlexItem>
											) }
										</Flex>
									</Button>
								</FlexItem>
							</Flex>
						);
					} ) }
				</div>
			</div>
			{ recommendedChannels.length > 0 && (
				<div className="woocommerce-marketing-add-channels">
					<Flex direction="column">
						<FlexItem>
							<Button
								variant="link"
								onClick={ () => setCollapsed( ! collapsed ) }
							>
								{ __(
									'Add channels for other campaign types',
									'woocommerce'
								) }
								<Icon
									icon={ collapsed ? chevronDown : chevronUp }
									size={ 24 }
								/>
							</Button>
						</FlexItem>
						{ ! collapsed && (
							<FlexItem>
								<RecommendedChannelsList
									recommendedChannels={ recommendedChannels }
								/>
							</FlexItem>
						) }
					</Flex>
				</div>
			) }
		</Modal>
	);
};